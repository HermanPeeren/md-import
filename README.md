# MD Import

A Joomla component that imports the Joomla help pages from
[Jdocmanual](https://jdocmanual.org/en/jdocmanual?article=help/help-screens/start-here)
into a site. Every page becomes an article, every screenshot is copied into the
images folder, and the manual's table of contents becomes a menu.

The pages are Markdown, kept on GitHub in
[ceford/cefjdemos-data-jdm-help-en](https://github.com/ceford/cefjdemos-data-jdm-help-en).
They are converted to HTML with [league/commonmark](https://commonmark.thephpleague.com/),
which the package installs as a Joomla library.

No GitHub account or token is needed. The import downloads the repository as
one archive from GitHub's public download server: a single GET, which is not
subject to the API's rate limit.

## What you get

For the English manual, around 280 articles, 520 images and 330 menu items:

- **Articles** in a *Help* category, with a subcategory per section of the
  manual (Articles, Banners, Menu Items › Articles, …). Each article keeps the
  page's description as its meta description. It ends with a line saying where
  it came from and under which licence.
- **Images** in `images/jdm-help/`, in the same folders as in the repository.
- **Menu items** under a menu item you choose: a heading per section, and a
  Single Article item per page, in the manual's own order.
- **Links between pages** point at the imported articles, so they use the menu
  items' URLs. Links to the other manuals (the User Manual, for one) go to
  jdocmanual.org.

Importing again updates what was imported before; it does not make copies.
An article whose text has not changed is not saved again. Articles or menu
items you deleted are made again.

## Installing

Download `pkg_mdimport-<version>.zip` from the
[releases](https://github.com/HermanPeeren/md-import/releases) and install it
under *System › Install › Extensions*. The package holds two extensions:

| Extension | What it is |
|---|---|
| `com_mdimport` | the component |
| `lib_yepr_commonmark` | league/commonmark and its dependencies, as a Joomla library |

It needs Joomla 5 or 6 and PHP 8.1 or later, with the zip extension.

## Using it

1. Make a menu item to hang the manual under. A *Menu Heading* called *Help*
   in your main menu works well.
2. Open *Components › MD Import › Options* and choose it as the
   **Parent Menu Item**. Check the other options while you are there.
3. Go back to *Components › MD Import* and press **Start Import**. Keep the
   page open until it says *Finished*. It works in batches, so a short
   `max_execution_time` is no problem.

### Options

| Tab | Option | Default |
|---|---|---|
| Import | Parent Menu Item | — (required) |
| | Section Menu Items: heading, category list or category blog | Menu Heading |
| | Images Folder (an existing folder under `images/`) and Subfolder | `images/` + `jdm-help` |
| | Category; Section Categories | a new *Help* category; yes |
| | Status, Access, Language | Published, Public, All |
| | Imported Before: update or skip | Update |
| | Source Line | yes |
| Source | Owner, Repository, Branch or Tag | `ceford`, `cefjdemos-data-jdm-help-en`, `2.0` |
| | Online Manual | `https://jdocmanual.org/en/` |
| Markdown | Flavour: CommonMark or GitHub-Flavoured | GitHub-Flavoured |
| | HTML Input: allow, escape, strip | Allow |
| | Allow Unsafe Links; Maximum Nesting Level | no; 0 (no limit) |
| | Extensions: Attributes, Description Lists, External Links, Footnotes, Heading Permalinks, Smart Punctuation, Table of Contents, and, for plain CommonMark, Autolink, Strikethrough, Tables, Task Lists | none |

The Markdown options are the ones the
[Obix CommonMark content plugin](https://www.obix.nl/nl/joomla/joomla-extensies-door-obix/commonmark-content-plugin)
offers, so they will be familiar to anyone who knows that plugin.

The same manual exists in other languages, in repositories called
`cefjdemos-data-jdm-help-de`, `-fr`, `-nl`, `-es` and so on. Point **Repository**
at one of those to import that language instead.

### Showing the menu

A Menu Heading's children only show in a menu module whose *Show Sub-menu
Items* is on, or with *End Level* set deep enough. On a fresh Cassiopeia site,
change that on the Main Menu module.

## Using the library on its own

`lib_yepr_commonmark` is an ordinary Joomla library. Any extension can use it:

```php
require_once JPATH_LIBRARIES . '/yepr_commonmark/autoload.php';

$html = (new League\CommonMark\GithubFlavoredMarkdownConverter())->convert($markdown);
```

## Licence

MD Import is GPL-3.0-or-later. league/commonmark and its dependencies keep
their own licences (BSD-3-Clause and MIT).

The help pages are published under the
[Joomla! Electronic Documentation License (JEDL)](https://docs.joomla.org/JEDL),
which allows them to be republished. The source line that ends every imported
article is how this component gives the attribution that licence asks for.

## Developing

See [docs/development.md](docs/development.md).
