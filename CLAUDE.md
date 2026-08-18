# Flashy Session Context

## What this app is

Flashy is a plain PHP flashcard app with no framework. It is designed for studying a target language against English.

Core learner flow:
- User chooses a language.
- User chooses either:
  - `All` cards for that language,
  - a created set,
  - or a smart/virtual set.
- Study shows one word and the learner types the translation.
- Matching is exact after normalization to lowercase.
- Aliases are supported for both the target language and English.

## Tech stack

- Plain PHP
- Tailwind CSS via npm build
- SQLite for local development
- MySQL for production / 20i installs
- Root entry point: `index.php`

## Database behavior

- Local development can use SQLite.
- Production should use MySQL.
- Installation / schema setup is handled by the app install + migration flow.
- Schema creation and upgrades live in `app/Core/Migrator.php`.

Main tables:
- `users`
- `languages`
- `sets`
- `cards`
- `user_card_progress`
- `user_card_attempts`

## Main concepts

### Languages

- A language contains published sets.
- Language page shows:
  - smart sets across all published sets in that language
  - an `All` card for all published cards
  - created sets in a 4-column grid on desktop

### Sets

- Each set belongs to one language.
- Sets can be published or hidden by admin.
- Users can study the whole set or set-specific smart sets.

### Cards

- Cards store:
  - target-language word in `gaidhlig`
  - English word in `english`
  - optional `language_aliases`
  - optional `english_aliases`
- CSV import/export exists for sets.

### Smart / virtual sets

Supported smart sets:
- New
- Difficult
- Medium
- Easy
- Incorrect recently
- Mastered
- Skipped recently
- Needs review
- Improving
- Unstable

Important:
- Smart sets are based on the current user’s own history, not shared/global history.
- Smart sets require login.
- Guest mode stores no database progress.
- Empty smart sets are shown disabled with an explanatory message.

## Study behavior

### Modes

Normal sets can use:
- `bilingual`
- `to_english`
- `to_language`

Study session options:
- `finite`
- `infinite`

Incorrect-answer handling:
- `stay`
- `advance`

Current defaults:
- Translation: `bilingual`
- Study mode: `finite`
- Wrong answers: `stay`

### Smart-set study rules

- Smart sets always study in bilingual mode.
- Translation direction controls are hidden on smart-set study pages.
- Smart-set progress considers both directions separately:
  - target language -> English
  - English -> target language

### Answer checking

- Inputs and displayed words are normalized to lowercase.
- Exact match only after normalization.
- Aliases are accepted as alternate exact answers.

### Session UX

- Session history is horizontal and draggable.
- Correct answer animation: card flies right.
- Skip / wrong-advance animation: card flies left.
- Wrong-stay animation: card shakes, goes red, then returns to slate.

## Progress tracking

- Logged-in users store progress in:
  - `user_card_progress`
  - `user_card_attempts`
- Guest sessions do not persist progress.
- Attempts now record translation direction with `translation_direction`.
- Legacy attempts without direction are treated as applying to both directions.

## Authentication

- Users can register and log in as learners.
- Admins log in through the normal user system.
- If a logged-in user is admin, admin access is available in the profile dropdown.
- Admin login is not shown as a separate navbar action.

## Admin area

Admin features include:
- create/edit/delete languages
- create/edit/delete sets
- publish/hide sets
- manage cards
- import/export CSV for sets
- create admin users
- run migrations
- import SQL
- inspect current database configuration

## UI conventions

- Slate-based palette, not green as a general theme
- Dark slate action buttons
- Public pages use a consistent max width with the header
- Back buttons are dark slate, rounded, with a left arrow
- Mobile navigation is a full-screen modal
- Study page has a mobile settings modal / settings bar

## Important files

- `index.php`
- `app/Core/Database.php`
- `app/Core/Migrator.php`
- `app/Controllers/PublicController.php`
- `app/Repositories/LanguageRepository.php`
- `app/Repositories/SetRepository.php`
- `app/Repositories/UserProgressRepository.php`
- `app/Support/StudySession.php`
- `views/public/*.php`
- `views/admin/*.php`
- `views/resources/css/app.css`

## Recent important behavior

- Language `All` card count comes from repository aggregate counts.
- Smart sets now log and classify by translation direction.
- Smart-set study hides translation direction controls.
- Export/edit admin set buttons were normalized to match other admin buttons.

## Working rules for future sessions

- Keep the app framework-free.
- Prefer small targeted changes over broad rewrites unless necessary.
- Preserve the current visual language and spacing consistency.
- When changing study behavior, check:
  - `PublicController`
  - `StudySession`
  - `UserProgressRepository`
  - related public views
- When changing counts shown in the UI, verify the repository query actually returns the field used by the view.
