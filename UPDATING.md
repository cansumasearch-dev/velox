# Velox — install & keep it updated

Everything you need to install Velox, push updates, and let your friends
auto-update — while the plugin stays private to the people you choose.

---

## 0. The 30-second mental model

- Velox lives in a **GitHub repo** (`JustKyrix/velox`).
- Every time you want to ship an update, you **bump the version number** and
  **publish a release**. A GitHub Action builds the zip for you automatically.
- Every site that has Velox installed checks that repo a few times a day and
  shows a normal **"Update now"** button in WP Admin → Plugins — just like any
  plugin from wordpress.org.
- If the repo is **private**, each site needs a read-only GitHub token pasted
  into Velox → Settings. That's the whole "invisible to the public" trick.

---

## 1. First-time install (you, and each friend)

1. Download the latest `velox.zip` (from the repo's **Releases** page, or the
   one you were handed).
2. WordPress Admin → **Plugins → Add New → Upload Plugin**.
3. Choose `velox.zip` → **Install Now** → **Activate**.
4. You'll see a new **Velox** item in the left admin menu. Done.

Velox ships with every aggressive feature **OFF**. Nothing about your site
changes until you flip a toggle. Turn things on one at a time from the
**Performance** and **Settings** tabs, and re-test PageSpeed after each.

> Plesk note: nothing to configure in Plesk. Velox is a normal plugin inside
> WordPress. The only Plesk thing worth doing once is taking a **database
> backup** before your first big Database-tab cleanup.

---

## 2. Put the code on GitHub (one time)

If you're starting from the zip and don't have the repo yet:

```bash
# from inside the unzipped velox/ folder
git init
git add .
git commit -m "Velox 1.0.0"
git branch -M main
git remote add origin https://github.com/JustKyrix/velox.git
git push -u origin main
```

If your GitHub username or repo name is different, change these two lines at
the top of `velox.php` so the auto-updater points at the right place:

```php
define( 'VELOX_GH_USER', 'JustKyrix' );
define( 'VELOX_GH_REPO', 'velox' );
```

(The `GitHub Plugin URI:` line in the header and the `Plugin URI:` are cosmetic
— nice to keep in sync, but the two `define()`s above are what actually drive
updates.)

---

## 3. Public vs private repo

**Public repo** — simplest. Updates just work on every site, no token needed.
Anyone *could* find the code, but they'd have to go looking.

**Private repo** — this is what you want for "us only". The code is invisible to
the public. Each install needs a token to download updates (next section).

You can start private now and flip the repo to public later in GitHub settings
without changing anything in the plugin.

---

## 4. The read-only token (private repo only)

Make a **fine-grained personal access token** that can only *read* this one
repo. Never use your full-access token.

1. GitHub → **Settings → Developer settings → Personal access tokens →
   Fine-grained tokens → Generate new token**.
2. **Repository access** → *Only select repositories* → pick `velox`.
3. **Permissions** → Repository permissions → **Contents: Read-only**.
4. Generate, copy the `github_pat_…` string.
5. In each site: **Velox → Settings → Updates**, paste it into the GitHub token
   field, **Save settings**.

That token only lets a site read this one repo's releases. Nothing else.

> Sharing with friends: give each friend the token (or make one token per
> person so you can revoke individually). They paste it once in Velox →
> Settings and never think about it again.

---

## 5. Shipping an update (the loop you'll actually repeat)

Say you changed some code and want everyone to get it.

1. **Bump the version in two places** so they match:
   - the `Version:` header in `velox.php` (e.g. `1.0.1`)
   - `define( 'VELOX_VERSION', '1.0.1' );`
2. Commit and push:
   ```bash
   git add .
   git commit -m "1.0.1 — fix X, add Y"
   git push
   ```
3. **Tag it and push the tag** — this is what triggers the release:
   ```bash
   git tag v1.0.1
   git push origin v1.0.1
   ```
4. The included GitHub Action (`.github/workflows/release.yml`) automatically
   builds a clean `velox.zip` and publishes it as release **v1.0.1**.

That's it. Within a few hours every site sees the update. To get it instantly
on a given site, go to **Velox → Settings → "Check for updates now"** (or WP
Admin → Dashboard → Updates), and the normal **Update now** button appears.

> Version rule: the tag, the `Version:` header, and `VELOX_VERSION` should all
> be the same number. The header is what WordPress compares against; the tag is
> what names the release; keeping them identical keeps you sane.

### Don't want to use tags from the terminal?

You can also click **Releases → Draft a new release** in GitHub, type a tag like
`v1.0.1`, and publish. The Action still runs and attaches the zip. Just remember
to bump the version in `velox.php` first, or sites won't see it as "newer".

---

## 6. How a friend gets the update

Nothing for them to do. Their site checks the repo on WordPress's normal
schedule and shows **Update now** under Plugins. They click it. If the repo is
private, this only works while their token is still valid — so don't revoke a
friend's token unless you mean to cut them off.

---

## 7. Keeping it private (summary)

- Repo set to **Private** on GitHub.
- Each install authenticated with a **read-only fine-grained token**.
- Not listed on wordpress.org, so it never shows up in the public plugin
  directory or search.
- To off-board someone: delete or regenerate the token they were using.

---

## 8. Quick troubleshooting

- **"No update showing"** → version in `velox.php` header didn't go up, or the
  release/tag name is older/equal. Bump it, re-tag.
- **Private repo, update fails to download** → token missing, expired, or lacks
  *Contents: Read-only* on this repo. Re-paste in Velox → Settings.
- **Update check feels stale** → use **Check for updates now** in Velox →
  Settings; it forces a fresh look (Velox caches release info for ~6 hours).
- **WebP buttons greyed out** → the server has neither Imagick-with-WebP nor
  GD-with-WebP. Ask the host (or enable the PHP `imagick`/`gd` extension in
  Plesk → PHP settings). Velox tells you which engine it found on the Dashboard.

---

Built for the Oxygen + WP Fastest Cache + Cloudflare stack. Velox stays out of
the way of your cache plugin on purpose — it only adds what they don't already
do.
