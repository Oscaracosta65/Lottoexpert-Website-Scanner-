# com_lesiteaudit — LottoExpert Site Audit Component

A native Joomla 4/5 administrator component that replaces the Sourcerer-embedded article scanner. Accessible only to users with the `core.admin` privilege (Super Administrators).

## URL

`/administrator/index.php?option=com_lesiteaudit`

## Directory structure (inside this `plugin/` folder)

```
com_lesiteaudit.xml                 — Component manifest (install this via Joomla)
admin/
  services/
    provider.php                    — Joomla DI service provider
  src/
    Extension/
      LeSiteAudit.php               — Extension class (bootstraps the component)
    Controller/
      DisplayController.php         — All PHP scanner logic + action dispatch
    View/
      Scanner/
        HtmlView.php                — View class; sets toolbar title and exposes variables
  tmpl/
    scanner/
      default.php                   — HTML/CSS/JS template (native PHP, no Sourcerer tags)
```

## Installation

1. **Zip the contents** — zip the files so that `com_lesiteaudit.xml` is at the root of the archive:
   ```
   com_lesiteaudit.xml
   admin/
     services/provider.php
     src/...
     tmpl/...
   ```
2. **Install via Joomla** — go to *System → Install → Extensions*, upload the zip, click Install.
3. **Navigate to the tool** — *Components → Site Audit* in the Joomla admin menu, or visit `/administrator/index.php?option=com_lesiteaudit` directly.

## Why a native component instead of Sourcerer

The Sourcerer-embedded version suffered from PHP syntax errors triggered by Sourcerer's internal PHP parser rewriting constructs like `foreach`, `match`, and multi-line closures. A native component bypasses Sourcerer entirely — PHP files are parsed directly by the web server, so all standard PHP syntax works without restrictions.

## Differences from the Sourcerer article version

| Area | Sourcerer article | This component |
|---|---|---|
| Session storage | `$_SESSION` | Joomla session API (`Factory::getApplication()->getSession()`) |
| Access control | Security guard code block | Joomla `core.admin` ACL check in controller |
| Base URL | `Uri::current()` | `Route::_('index.php?option=com_lesiteaudit')` |
| PHP syntax | C-style loops (FORENSIC safe mode) | Standard `foreach`, `match`, arrow functions |
| HTML template | `[[tag]]` / `[[/tag]]` Sourcerer syntax | Native `<tag>` / `</tag>` |

## Configuration

Scanner config (site root, allowed hosts, batch size, etc.) is hardcoded in `DisplayController::display()` in the `$config` array near the top of that method. Edit those values to point at a different domain.
