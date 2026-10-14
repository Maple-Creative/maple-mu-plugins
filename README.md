# Maple must-use plugins

Must-use (mu) plugins that Maple Creative installs on every WordPress site in its Pressable account. WordPress loads a must-use plugin on every request. It never shows up in the Plugins screen, so a client can't switch it off, and it has no settings or updates to manage.

**This repo is public on purpose.** Pressable installs account mu-plugins by downloading each file from a URL without logging in, so the file has to be publicly readable. Nothing here is secret: the code is generic WordPress hardening, with no client names, credentials or site data. Keep it that way, and never commit anything site-specific here.

## Plugins

### `maple-user-privacy.php`

Stops logged-out visitors from finding out who has an account on the site.

**Why it exists.** In October 2026 we checked all 111 live care-plan sites without logging in. On 103 of them, anyone could get a list of user accounts, and on 75 of those, some account names were email addresses (e.g. `janedoebuildwithmaple-com`). That exposed staff and client emails to spammers and phishers, and handed over half of a password-guessing attempt. On some sites the embed (oEmbed) data even returned the author's full email address.

We first tested the WordPress.org plugin *Stop User Enumeration* on the two Halt the Harm sites. We chose this mu-plugin instead because:
- it also covers leaks Stop User Enumeration leaves open (oEmbed author data and the core users sitemap);
- clients can't deactivate it;
- one Pressable account setting covers every existing site and every new site automatically.

Ryan approved it on 2026-10-14. The full discussion is in Notion, in the task "Review and fix public user exposure across all Maple sites" (Maple Team Data → Actions).

**What it does, for logged-out visitors only:**

| Route | Before | After |
|---|---|---|
| `/wp-json/wp/v2/users` and `/wp/v2/users/<id>` (also via `?rest_route=`) | Full user list with slugs | 404 (some sites already return 401 from another plugin) |
| `/?author=1` (also `?author[]=1`, `/feed/?author=1`) | Redirects to `/author/<slug>/` | 404 |

**What it does for everyone:**

| Route | Before | After |
|---|---|---|
| oEmbed (`/wp-json/oembed/1.0/embed`) | `author_name`, `author_url` | Author fields removed |
| Core sitemap `wp-sitemap-users-*.xml` | Lists author URLs | Disabled |

**What it does not change:**
- Logged-in users see no difference. The block editor's author picker, Jetpack, application-password and REST clients (including Claude's maple-editor connection) all work as before.
- `/author/<slug>/` archive pages still work, because some sites use them.
- It doesn't fix email-style slugs that are already public in author links, Rank Math/Yoast markup or author sitemaps. The fix for those is renaming each user's `user_nicename` (the public slug only; logins, passwords and emails stay the same). That's a separate per-site script and isn't part of this repo.
- XML-RPC stays open on purpose, because Jetpack needs it.

## How it's deployed

The file is registered as a **Pressable account mu-plugin** (Pressable API / MCP `create_account_mu_plugin`, set to apply to both existing and future sites). Pressable then installs it on each site as a must-use plugin.

The registered URL points at a **version tag**, not `main`, so changes here never reach live sites by accident:

```
https://raw.githubusercontent.com/Maple-Creative/maple-mu-plugins/v1.0.0/maple-user-privacy.php
```

The registration covers every live site on the account, including staging copies and the few non-care-plan sites, and not just care-plan sites. That was decided on purpose.

## Checking a site

Run these logged out, with a cache-buster on the end:

```bash
UA='Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36'
curl -s -o /dev/null -w '%{http_code}\n' -A "$UA" "https://SITE/wp-json/wp/v2/users?cb=$RANDOM"   # expect 404 or 401
curl -s -o /dev/null -w '%{http_code}\n' -A "$UA" "https://SITE/?author=1&cb=$RANDOM"             # expect 404
```

Use a full browser user agent. Pressable's edge returns **429** to a bare `Mozilla/5.0` or curl user agent, and that looks like a pass when it isn't.

## Changing or removing it

1. Edit the file, bump the `Version:` header, commit and tag (e.g. `v1.0.1`).
2. Register the new tag's raw URL with Pressable, then remove the old registration. Using a new URL for each version makes every update a deliberate step; don't rely on Pressable re-fetching an existing URL.
3. Spot-check a few sites with the commands above.

**To roll back:** register the previous tag's URL. **To remove it everywhere:** delete the account mu-plugin in Pressable and spot-check a few sites to confirm it's gone (`wp plugin list --status=must-use`).

**If one site needs an exception** (e.g. a front-end feature that reads the public users API), it's safer to add a small site-specific mu-plugin that removes the relevant filter than to fork this file.
