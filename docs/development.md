# Developing MD Import

## Layout

`src/` holds the three extensions in the package, each laid out the way its
own installer reads it.

```
src/
  pkg_mdimport.xml              the package manifest: library first, then component
  script.php                    refuses a PHP or Joomla that is too old
  language/en-GB/               the package's own .sys.ini
  component/                    com_mdimport, as its zip
    mdimport.xml
    administrator/components/com_mdimport/
      config.xml                the options
      src/Source/               reads a Jdocmanual repository: sections, pages, images
      src/Markdown/             CommonMark conversion, and where links and images go
      src/Import/               the import: settings, download, mapping table, writers, steps
      src/Service/              the MVC factory, and the providers that build the services
      src/{Controller,Model,View}/  one screen, one JSON task
      tmpl/ language/ services/ sql/
    media/com_mdimport/js/import.js   drives the steps
  library/yepr_commonmark/      lib_yepr_commonmark: composer.json, autoload.php, vendor/
tests/Unit/                     PHPUnit
tests/Fixtures/help-repo/       a cut-down Jdocmanual repository
cypress/e2e/                    the import, end to end, on a real Joomla
build/                          build.php, update-xml.php
tools/                          install-local.php, seed-site.php, phpstan-bootstrap.php
```

`Source/` and `Markdown/` do not touch Joomla, and the unit tests cover them
against the fixture. `Import/` writes through Joomla's own models, so only
Cypress can say whether it works.

## Services, and how the MVC classes get them

The import's services are registered in `src/Service/Provider/Import.php`, and
nothing else in the component constructs one:

| Service | Built from |
|---|---|
| `ImportSettings` | the component's options |
| `ItemMap` | the database |
| `ContentWriter` | the application and the database |
| `Downloader` | Joomla's CA bundle |
| `MarkdownConverter` | the settings' conversion options, after loading lib_yepr_commonmark |
| `Importer` | all of the above, the tmp folder and the site root |

Each is shared and built the first time something asks for it.

Joomla builds controllers and models itself, with the constructors Joomla
decides, so they get services the way core hands over the database: the class
implements an `*AwareInterface`, usually with the matching trait, and the
component's MVC factory (`Service\MVCFactory`, a subclass of Joomla's,
registered by `Service\Provider\MVCFactory` in place of Joomla's provider)
hands the service over after building it. This is the same pattern as in
Exten-gen. `ImportModel` declares all three: settings, item map and importer.

The importer is handed over as a closure that fetches it, not as the importer
itself. Building it loads CommonMark, and the import screen has to open and
report a missing library without loading it.

`ServiceConstructionTest` reads the source. It fails on a construction of
one of these services anywhere but the provider, and on any `new` in a
controller, model, view or layout, apart from exceptions and Joomla's
`JsonResponse`. What the importer still makes for itself is per-run values:
the `HelpRepository` over this download and the `UrlResolver` over this run's
articles.

## The library's vendor tree

`src/library/yepr_commonmark/vendor/` is not in git. `composer install` at the
root installs it, because a post-install script runs `composer install --no-dev`
in the library folder. The unit tests load CommonMark from there and not from
a copy of their own, so they test what ships.

To move to a newer league/commonmark, run `composer update` in the library
folder.

## How the import runs

`media/com_mdimport/js/import.js` posts `task=import.step&step=…&offset=…` and
follows the `next` step the server answers with:

| Step | What it does | Batch |
|---|---|---|
| download | fetch `codeload.github.com/<owner>/<repo>/zip/<branch>` to `tmp/mdimport`, unpack | 1 |
| images | copy `images/**` to the images folder | 100 |
| categories | the *Help* category, and one per section | all |
| articles | an (empty) article for every page that has none | 40 |
| content | convert each page and save it into its article | 20 |
| menu | section items, parents first, then page items | 40 |
| cleanup | remove `tmp/mdimport` | 1 |

Articles come before content because pages link to pages that come after them,
and a link needs an article id to point at. Links are written as
`index.php?option=com_content&view=article&id=…&catid=…`. Joomla's SEF plugin
turns those into the menu item's URL when the page is shown.

`#__mdimport_items` remembers what each page and section became. That is what
makes a second import update instead of duplicate. The `hash` column skips
saving an article whose text has not changed, so that re-importing does not
fill every article's version history.

### Things that were not obvious

- **One model per save.** An AdminModel remembers the id it last saved, and a
  "new" item saved through the same instance overwrites the previous one.
  `ContentWriter` creates a fresh model every time.
- **`menu.json` nests once.** *Menu Items* is section › subsection › page.
  Every other section is section › page. `HelpRepository` reads both shapes.
- **Slugs are unique across the manual,** so links (`help/<section>/<slug>`)
  are resolved by slug alone. The section in a link is sometimes flattened
  (`menu-items-common`) and sometimes not.
- **The download is ~55 MB.** It is streamed to disk with curl, using Joomla's
  own CA bundle, because Joomla's Http client keeps the whole response in
  memory and WAMP's PHP has no CA bundle configured.

## The development site

`/joomla`, git-ignored, served at `http://localhost/md-import/joomla`, with its
own database `jlatest-md-import` (prefix `mdi_`).

```bash
composer install-local     # build, then install through Joomla's CLI
php tools/seed-site.php    # a "Help" menu heading, set as the Parent Menu Item
```

Cypress signs in from `cypress.env.json`, which is git-ignored. Copy
`cypress.env.json.dist` and fill it in.

## Before committing

```bash
composer test        # phpunit
composer analyse     # phpstan, level 6, against /joomla
composer cs          # phpcs, PSR-12
npx cypress run      # the real import on the development site; takes a few minutes
```

## Releasing

1. Bump `<version>` in `src/pkg_mdimport.xml`, `src/component/mdimport.xml` and
   `src/library/yepr_commonmark/yepr_commonmark.xml`. `ReleaseTest` checks
   that they agree.
2. `composer update-xml`, and commit.
3. Push a tag `v<version>`. The release workflow checks the tag against the
   manifest, runs every check, builds `pkg_mdimport-<version>.zip`, publishes
   the release, and commits the archive's checksum into `updates.xml` on `main`.
