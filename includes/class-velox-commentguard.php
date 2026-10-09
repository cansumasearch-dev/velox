<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Velox Comment protection — keep spam comments out, and make sure nothing a
 * stranger writes in a comment can ever be clicked.
 *
 * Three layers, each on its own switch:
 *
 *  1. KEEP BOTS OUT. Nearly all comment spam is posted straight to
 *     wp-comments-post.php by scripts that never render the page. Every comment
 *     form gets a token that only a real browser running the page's JavaScript
 *     fills in (and only after a couple of seconds), plus a hidden honeypot field
 *     that only bots fill. Fail either and the comment is refused before it is
 *     ever stored. No CAPTCHA, nothing for real visitors to do. The token is
 *     static per site, so it survives page caching.
 *
 *  2. SEND LINK SPAM TO THE SPAM FOLDER. What gets past (humans paid to spam)
 *     almost always carries links. Comments with more links than allowed, or
 *     forum [url=] codes, are marked spam instead of waiting for approval.
 *
 *  3. NOTHING IS CLICKABLE. The real danger in a spam comment is its link.
 *     Links from visitors are shown as plain text — on the site AND in wp-admin —
 *     the author "website" link is dropped, and the notification emails get
 *     defanged addresses (hxxps://example[.]com) so a mail app can't link them.
 *     Comments by people who can moderate keep their links.
 *
 * @package Velox
 */
class Velox_Comment_Guard {

	const OPT_BLOCKED = 'velox_cg_blocked';   // array( 'total' => int, 'days' => array( 'Y-m-d' => int ) )
	const CRON        = 'velox_cg_cleanup';
	const MIN_SECONDS = 2;                    // faster than this from page load = a script

	public static function init() {
		// The daily clean-up must be unscheduled even when the tool is off.
		add_action( self::CRON, array( __CLASS__, 'cleanup' ) );
		if ( ! Velox_Settings::get( 'util_commentguard', true ) ) {
			wp_clear_scheduled_hook( self::CRON );
			return;
		}
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}

		// 1 + 2: submissions.
		add_filter( 'preprocess_comment', array( __CLASS__, 'screen' ), 1 );
		add_filter( 'pre_comment_approved', array( __CLASS__, 'classify' ), 99, 2 );
		if ( Velox_Settings::get( 'cg_block_bots', true ) ) {
			add_action( 'comment_form_after_fields', array( __CLASS__, 'honeypot_field' ) );
			add_action( 'wp_footer', array( __CLASS__, 'form_script' ), 50 );
		}
		if ( Velox_Settings::get( 'cg_remove_url_field', true ) ) {
			add_filter( 'comment_form_default_fields', array( __CLASS__, 'drop_url_field' ) );
		}

		// 3: nothing clickable.
		if ( Velox_Settings::get( 'cg_strip_links', true ) ) {
			add_filter( 'comment_text', array( __CLASS__, 'unlink_text' ), 99, 2 );
			add_filter( 'get_comment_author_url', array( __CLASS__, 'drop_author_url' ), 99, 3 );
			add_filter( 'comment_moderation_text', array( __CLASS__, 'defang' ), 99 );
			add_filter( 'comment_notification_text', array( __CLASS__, 'defang' ), 99 );
		}

		// Don't need comments at all? Same switch as Performance → General, but it
		// works here even when the Performance module is off.
		if ( Velox_Settings::get( 'perf_disable_comments', false ) && ! Velox_Settings::get( 'module_performance' ) ) {
			add_filter( 'comments_open', '__return_false', 20 );
			add_filter( 'pings_open', '__return_false', 20 );
		}
	}

	/* ------------------------------------------------------------ submissions */

	/** Static per-site token: survives page caching, useless to anyone who didn't run our script. */
	public static function token() {
		return substr( wp_hash( 'velox-comment-guard|' . home_url() ), 0, 20 );
	}

	/** Trusted authors skip the bot checks (admins replying from the dashboard, members). */
	private static function trusted() {
		return is_user_logged_in() && current_user_can( 'moderate_comments' );
	}

	/**
	 * preprocess_comment: refuse obvious bots before anything is stored.
	 * Logged-in members skip the browser checks but not the link rules.
	 */
	public static function screen( $data ) {
		$type = isset( $data['comment_type'] ) ? (string) $data['comment_type'] : '';
		if ( in_array( $type, array( 'pingback', 'trackback' ), true ) ) {
			if ( Velox_Settings::get( 'cg_block_pings', true ) ) {
				self::refuse( 'ping' );
			}
			return $data;
		}
		if ( self::trusted() || is_user_logged_in() || ! Velox_Settings::get( 'cg_block_bots', true ) ) {
			return $data;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- public comment form; the token below is the check.
		$honey = isset( $_POST['vx_cg_hp'] ) ? trim( (string) wp_unslash( $_POST['vx_cg_hp'] ) ) : '';
		$token = isset( $_POST['vx_cg'] ) ? (string) wp_unslash( $_POST['vx_cg'] ) : '';
		$spent = isset( $_POST['vx_cg_t'] ) ? (int) $_POST['vx_cg_t'] : 0;
		// phpcs:enable
		if ( '' !== $honey ) {
			self::refuse( 'honeypot' );
		}
		if ( ! hash_equals( self::token(), $token ) ) {
			self::refuse( 'no_browser' );
		}
		if ( $spent < self::MIN_SECONDS * 1000 ) {
			self::refuse( 'too_fast' );
		}
		return $data;
	}

	/** Count it, then stop. The message is for the rare real person whose browser blocks scripts. */
	private static function refuse( $why ) {
		self::count_blocked();
		do_action( 'velox_comment_blocked', $why );
		// Visitors see this, and Velox's dictionary only runs inside wp-admin — so
		// follow the site's own language directly.
		$de = 0 === strpos( (string) ( function_exists( 'determine_locale' ) ? determine_locale() : get_locale() ), 'de' );
		wp_die(
			esc_html( $de
				? 'Ihr Kommentar wirkte automatisiert und wurde deshalb nicht gesendet. Wenn Sie ihn selbst geschrieben haben, aktivieren Sie JavaScript, warten Sie einen Moment und senden Sie ihn erneut.'
				: __( 'Your comment looked automated, so it was not sent. If you wrote it yourself, make sure JavaScript is switched on, wait a moment and send it again.', 'velox' ) ),
			esc_html( $de ? 'Kommentar nicht gesendet' : __( 'Comment not sent', 'velox' ) ),
			array( 'response' => 403, 'back_link' => true )
		);
	}

	public static function count_blocked() {
		$b = get_option( self::OPT_BLOCKED, array() );
		$b = is_array( $b ) ? $b : array();
		$b['total'] = isset( $b['total'] ) ? (int) $b['total'] + 1 : 1;
		$day        = gmdate( 'Y-m-d' );
		$b['days']  = isset( $b['days'] ) && is_array( $b['days'] ) ? $b['days'] : array();
		$b['days'][ $day ] = isset( $b['days'][ $day ] ) ? (int) $b['days'][ $day ] + 1 : 1;
		krsort( $b['days'] );
		$b['days'] = array_slice( $b['days'], 0, 30, true );
		update_option( self::OPT_BLOCKED, $b, false );
	}

	/**
	 * pre_comment_approved: what got past the bot checks but still looks like
	 * spam goes straight to the spam folder (never "pending", never emailed).
	 */
	public static function classify( $approved, $data ) {
		if ( 'spam' === $approved || 'trash' === $approved || is_wp_error( $approved ) ) {
			return $approved;
		}
		if ( ! empty( $data['user_id'] ) && user_can( (int) $data['user_id'], 'moderate_comments' ) ) {
			return $approved;
		}
		$text = (string) ( $data['comment_content'] ?? '' );
		$url  = (string) ( $data['comment_author_url'] ?? '' );
		return self::looks_like_spam( $text, $url ) ? 'spam' : $approved;
	}

	/** Shared by new comments and the clean-up scan. */
	public static function looks_like_spam( $text, $author_url = '' ) {
		$max   = max( 0, (int) Velox_Settings::get( 'cg_max_links', 1 ) );
		$links = self::count_links( $text ) + ( '' !== trim( $author_url ) && Velox_Settings::get( 'cg_remove_url_field', true ) ? 1 : 0 );
		if ( $links > $max ) {
			return true;
		}
		// Forum link codes never belong in a WordPress comment.
		return (bool) preg_match( '/\[(?:url|link)\s*=|\[\/url\]/i', $text );
	}

	private static function count_links( $text ) {
		return (int) preg_match_all( '#(?:https?:)?//[^\s<>"\']+|\bwww\.[a-z0-9\-]+\.[a-z]{2,}#i', (string) $text );
	}

	/* ------------------------------------------------------------- the form */

	/** The honeypot: hidden from people (and screen readers), irresistible to form-filling bots. */
	public static function honeypot_field() {
		echo '<p class="vx-cg-hp" aria-hidden="true" style="position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden"><label for="vx_cg_hp">' . esc_html__( 'Leave this field empty', 'velox' ) . '</label><input type="text" name="vx_cg_hp" id="vx_cg_hp" value="" tabindex="-1" autocomplete="off"></p>' . "\n";
	}

	/**
	 * Adds the token + time spent to EVERY form that posts to wp-comments-post.php —
	 * comment_form(), Oxygen's comment form element and hand-built theme forms alike.
	 * Only printed where comments can actually be posted.
	 */
	public static function form_script() {
		if ( is_user_logged_in() || ! is_singular() || ! comments_open() ) {
			return;
		}
		?>
<script id="velox-comment-guard">(function(){var t0=Date.now(),k=<?php echo wp_json_encode( self::token() ); ?>;
function add(f,n,v){var i=f.querySelector('input[name="'+n+'"]');if(!i){i=document.createElement('input');i.type='hidden';i.name=n;f.appendChild(i);}i.value=v;}
document.addEventListener('submit',function(e){var f=e.target;if(!f||!f.action||f.action.indexOf('wp-comments-post.php')<0)return;add(f,'vx_cg',k);add(f,'vx_cg_t',String(Date.now()-t0));},true);})();</script>
		<?php
	}

	public static function drop_url_field( $fields ) {
		unset( $fields['url'] );
		return $fields;
	}

	/* --------------------------------------------------------- nothing clickable */

	/** Does this comment come from someone allowed to moderate (who may keep links)? */
	private static function from_moderator( $comment ) {
		return $comment && ! empty( $comment->user_id ) && user_can( (int) $comment->user_id, 'moderate_comments' );
	}

	/** comment_text: visitors' links become plain text (site and wp-admin). */
	public static function unlink_text( $text, $comment = null ) {
		if ( self::from_moderator( $comment ) || false === stripos( (string) $text, '<a' ) ) {
			return $text;
		}
		$out = preg_replace_callback(
			'#<a\b[^>]*>(.*?)</a\s*>#is',
			function ( $m ) {
				return '<span class="velox-unlinked">' . $m[1] . '</span>';
			},
			(string) $text
		);
		return null === $out ? wp_strip_all_tags( (string) $text ) : $out;
	}

	/** The author's "website" link — gone for visitors, so their name isn't a link either. */
	public static function drop_author_url( $url, $comment_id = 0, $comment = null ) {
		if ( '' === (string) $url ) {
			return $url;
		}
		if ( ! $comment && $comment_id ) {
			$comment = get_comment( $comment_id );
		}
		return self::from_moderator( $comment ) ? $url : '';
	}

	/** Emails: hxxps://example[.]com — readable, but no mail app turns it into a link. */
	public static function defang( $text ) {
		// WordPress's own Approve / Trash / Spam links point at this site — keep
		// those clickable so moderating from the email still works.
		$own  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$text = preg_replace_callback(
			'#\b(h)(tt)(ps?)(://)([^\s<>"\']+)#i',
			function ( $m ) use ( $own ) {
				$parts = explode( '/', $m[5], 2 ); // only the domain gets [.]
				$host  = strtolower( preg_replace( '/:\d+$/', '', $parts[0] ) );
				if ( '' !== $own && ( $host === $own || substr( $host, -strlen( '.' . $own ) ) === '.' . $own ) ) {
					return $m[0];
				}
				$host  = str_replace( '.', '[.]', $parts[0] );
				return $m[1] . 'xx' . $m[3] . $m[4] . $host . ( isset( $parts[1] ) ? '/' . $parts[1] : '' );
			},
			(string) $text
		);
		return preg_replace( '/\bwww\.([a-z0-9\-]+)\.([a-z]{2,})/i', 'www[.]$1[.]$2', (string) $text );
	}

	/* -------------------------------------------------------------- clean-up */

	/** What the screen shows: blocked so far, spam folder, waiting for approval. */
	public static function stats() {
		global $wpdb;
		$b = get_option( self::OPT_BLOCKED, array() );
		$week = 0;
		if ( ! empty( $b['days'] ) && is_array( $b['days'] ) ) {
			$cut = gmdate( 'Y-m-d', time() - 6 * DAY_IN_SECONDS );
			foreach ( $b['days'] as $d => $n ) {
				if ( $d >= $cut ) {
					$week += (int) $n;
				}
			}
		}
		return array(
			'blocked'      => isset( $b['total'] ) ? (int) $b['total'] : 0,
			'blocked_week' => $week,
			'spam'         => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ),
			'pending'      => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = '0'" ),
		);
	}

	/**
	 * Existing approved / pending comments that the current rules would call spam.
	 *
	 * @return array{count:int,items:array}
	 */
	public static function scan( $preview = 12 ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT comment_ID, comment_author, comment_author_url, comment_content, comment_date, comment_approved, user_id, comment_type
			 FROM {$wpdb->comments} WHERE comment_approved IN ('0','1') ORDER BY comment_ID DESC LIMIT 5000"
		);
		$hits = array();
		foreach ( (array) $rows as $r ) {
			$is_ping = in_array( $r->comment_type, array( 'pingback', 'trackback' ), true );
			if ( ! empty( $r->user_id ) && user_can( (int) $r->user_id, 'moderate_comments' ) ) {
				continue;
			}
			if ( $is_ping || self::looks_like_spam( $r->comment_content, $r->comment_author_url ) ) {
				$hits[] = $r;
			}
		}
		$items = array();
		foreach ( array_slice( $hits, 0, $preview ) as $r ) {
			$items[] = array(
				'id'      => (int) $r->comment_ID,
				'author'  => wp_strip_all_tags( $r->comment_author ),
				'excerpt' => self::defang( wp_trim_words( wp_strip_all_tags( $r->comment_content ), 24, '…' ) ),
				'date'    => mysql2date( get_option( 'date_format' ), $r->comment_date ),
				'status'  => '1' === (string) $r->comment_approved ? 'approved' : 'pending',
			);
		}
		return array( 'count' => count( $hits ), 'ids' => wp_list_pluck( $hits, 'comment_ID' ), 'items' => $items );
	}

	/** Move everything the scan finds into the spam folder (reversible from Comments → Spam). */
	public static function mark_scanned_spam() {
		$found = self::scan( 0 );
		$n     = 0;
		foreach ( array_slice( $found['ids'], 0, 500 ) as $id ) {
			if ( wp_spam_comment( (int) $id ) ) {
				$n++;
			}
		}
		return array( 'moved' => $n, 'remaining' => max( 0, $found['count'] - $n ) );
	}

	/**
	 * Permanently delete spam — all of it, or only older than $days. Batched so a
	 * spam folder with tens of thousands of entries can't time out the request.
	 */
	public static function delete_spam( $days = 0, $limit = 500 ) {
		global $wpdb;
		$sql = "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_approved = 'spam'";
		if ( $days > 0 ) {
			$sql .= $wpdb->prepare( ' AND comment_date_gmt < %s', gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) );
		}
		$ids = $wpdb->get_col( $sql . ' LIMIT ' . (int) $limit ); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ( $ids as $id ) {
			wp_delete_comment( (int) $id, true );
		}
		return array( 'deleted' => count( $ids ), 'remaining' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_approved = 'spam'" ) );
	}

	/** Daily: empty spam older than the chosen number of days. */
	public static function cleanup() {
		$days = (int) Velox_Settings::get( 'cg_autodelete_days', 7 );
		if ( $days > 0 && Velox_Settings::get( 'util_commentguard', true ) ) {
			self::delete_spam( $days, 2000 );
		}
	}
}
