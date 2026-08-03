# AB InBev technical test — Voting System

Technical test for a Drupal developer position at AB InBev: a simple voting system featuring Drupal-based administration and a REST API for consumption by third-party applications.

The module is located at  [`web/modules/custom/voting_system`](web/modules/custom/voting_system). The API's OpenAPI specification is in [`voting_system.spec.yml`](web/modules/custom/voting_system/voting_system.spec.yml) — it can be imported directly into Postman (Import → File) to generate a collection with all endpoints, examples, and variables already organized.

Test user:

- login: `admin`
- senha: `admin`

## How to run the project

The project comes pre-configured for both [Lando](https://lando.dev) and [DDEV](https://ddev.com). Use whichever you prefer—you don't need both.

### Using Lando

```bash
lando start
lando composer install
lando drush site:install --db-url=mysql://drupal10:drupal10@database/drupal10 -y
lando drush cr
lando db-import dump.sql.gz
```

The `site:install` command is only needed to generate `settings.php` with the correct connection details; the subsequent `db-import` replaces the content with the full dump (with the module already enabled, and entities and sample content included).

### Using DDEV

```bash
ddev start
ddev composer install
ddev import-db --file=dump.sql.gz
ddev drush cr
```

DDEV automatically generates `settings.ddev.php` with the database connection when you run `ddev start` and `ddev composer install`, so there is no need to run `site:install`—`import-db` already brings in the complete site.

After either workflow, the site is available at `https://abinbev-drupal-test.ddev.site` (DDEV) or at the URL provided by `lando start` in the terminal.

## What has already been implemented

### Data Modeling

- **VotingQuestion** — question (`title`, unique `question_id`, `show_percent`, `status`, author).
- **VotingAnswer** — answer reusable across questions (`title`, `description`, `image`).
- **VotingAnswerAssignment** — M:N relationship between question and answer; stores the `vote_count` for that pair.
- **VoteRecord** — a user vote for a specific `assignment_id` (ensures one vote per user per question).

### Administrative panel (Drupal)

- CRUD for questions (`/admin/voting-question`) featuring a custom form that allows linking existing answers during creation (using an "Add another answer" repeater).
- CRUD for answers (`/admin/voting-answer`).
- Read-only results page for administrators (`/admin/voting-results`), displaying counts and percentages per answer.
- "Voting Block" component, positionable in any region and configurable per question; it displays either the voting ballot or the results based on whether the current user has already voted, while respecting the `show_percent` configuration.
- Unique question identifier enforced by a custom validation constraint (`UniqueQuestionIdentifier`).

### REST API

Authentication via bearer token (`POST /oauth/token` with Drupal username/password).

| Method | Route | Access | Description |
|---|---|---|---|
| POST | `/oauth/token` | public | Obtains the access token |
| GET | `/api/voting/questions` | authenticated user | Lists active questions with answers |
| GET | `/api/voting/question/{question_id}` | authenticated user | A specific question |
| POST | `/api/voting/question` | admin | Creates a question |
| PATCH | `/api/voting/question/{question_id}` | admin | Updates a question (partial) |
| POST | `/api/voting/answer` | admin | Creates an answer (accepts optional `img_url`) |
| PATCH | `/api/voting/answer/{answer_id}` | admin | Updates an answer (only if not yet linked to any question) |
| POST | `/api/vote/{question_id}` | authenticated user | Registers a vote |
| GET | `/api/vote-results/{question_id}` | authenticated user | Results (only after the user votes) |

### Authentication and access control

- Custom token (`TokenService`/`TokenAuthService`), stored in a key-value store, expires in 1 hour.
- `TokenAuthenticationProvider` — a genuine Drupal authentication provider (using the `authentication_provider` tag), not merely an access check; this ensures that `\Drupal::currentUser()` reflects the token owner throughout the entire request (so question/answer authorship is recorded correctly, rather than defaulting to "anonymous").
- `VotingApiAccessCheck` — a custom access check (using the `access_check` tag) that manages `user` vs. `admin` permissions via the `_voting_system_api_access` route requirement.

### Business Rules

- Unique `question_id` (entity constraint + service-level check with a custom domain exception).
- One vote per user per question (`DuplicateVoteException`).
- Voting is only possible if the question is active (`QuestionNotActiveException`).
- The answer must belong to the question being voted on (`AnswerQuestionMismatchException`).
- Results are visible only to those who have already voted (`VoteRequiredException`); the percentage appears only if `show_percent` is enabled for the question.
- An answer can only be edited via the API if it is not linked to any question—since answers are reusable across questions, editing one that is already linked would retroactively alter what has already been displayed or voted on (`AnswerLinkedToQuestionException`).
- Answer image upload via URL: validates the URL format, content type (png/jpg/webp/gif), and size (5MB limit) before downloading and storing it as a managed Drupal file.

### Architecture and code quality

- Thin controllers: they only parse the request and delegate to services; domain exceptions are centrally mapped to the correct HTTP status (`ApiControllerBase`).
- Single-responsibility services: `QuestionResolverService` (resolves questions by numeric ID or `question_id`, avoiding duplication), `VoteService` (vote writing) separated from `VoteResultsService` (result reading).
- Drupal API caching correctly applied to the voting block (`user` context + cache tags on the involved entities), fixing a bug where one user's result leaked to others.
- Listing queries load answers and images in batches (`loadMultiple`), avoiding N+1 issues.
- Kernel tests for vote duplication and `question_id` uniqueness.

## Next steps (roadmap)

### Caching, queues, and performance

- Implement response caching at the API layer (currently, only the Drupal block is cached; JSON endpoints recalculate everything on every call).
- Move the answer image download process (`AnswerImageDownloaderService`) to a queue (`hook_queue_info`/`QueueWorker`) instead of downloading synchronously during the creation request—currently, a slow or large image URL stalls the API response.
- Review database indexes on high-volume tables (`vote_record`, `voting_answer_assignment`) as vote volume grows.
- Implement pagination for `GET /api/voting/questions` (currently, it returns all active questions at once).

### Security

- Flood control / rate limiting on `POST /oauth/token` — currently, there is no protection against username/password brute-force attacks, unlike Drupal's native login form.
- Output sanitization in `VoteBlock`: the answer title/description are concatenated into HTML markup without escaping (`Html::escape()`/`Xss::filter()`) before being passed to `Markup::create()` — a malicious answer title entered by a compromised admin would execute as HTML/JS in the voter's browser.
- `AnswerImageDownloaderService` accepts any URL provided by an admin for server-side download — it is worth considering an allowlist of hosts or blocking internal/private IPs to reduce the SSRF attack surface.
- Review token expiration/rotation (currently fixed at 1 hour, with no refresh token) and consider moving to a more standardized scheme (signed JWT or full OAuth2).

---

## Original test prompt

### System Description

The simple voting system allows users to vote on questions created by the administrator. The administrator can create questions with titles, identifiers, and answer options. Answer options can include an image, a title, and a brief description.
Users can vote on the created questions, and the administrator can later see the number of votes received for each question, along with the percentage of votes.
The administrator can configure voting to show or hide the total votes for each question after the user votes.
The administrator can configure the voting system to disable the voting system altogether.
The system must also be integrated with Drupal, allowing the votes to be displayed. Furthermore, the system must be able to provide an API so that authorized third-party applications can interact with the polls, allowing, for example, registered polls to be made available in an application with the full Drupal workflow experience.

### Functional Requirements

1. The administrator must be able to register questions with unique titles and identifiers.
2. Each question can have multiple answer options.
3. Answer options can include an image, a title, and a brief description.
4. Users should be able to vote on a question by selecting one of the answer options.
5. The system should record the votes received for each question and answer option.
6. The administrator should be able to view the total number of votes received for each question, along with the vote percentage.
7. The administrator can configure the voting system to be disabled.
8. The system should be integrated with Drupal to display the votes.
9. The administrator should be able to configure whether the vote total for each question should be displayed or hidden in Drupal after the user votes.
10. The system should provide an API so that authorized third-party applications can interact with the registered votes.

### Non-Functional Requirements

1. The system must be easy to use and intuitive for the administrator to register questions and answer options.
2. The system must be secure, protecting voting data and preventing improper manipulation.

### Technical Requirements

1. Do not use community modules to solve the problem, except for "restudy."
2. Do not use node for the tasks.
3. The code must be submitted to a GitHub repository, with a database dump and environment via lando.

Note that this test focuses exclusively on the development and operation of the system's backend.
Therefore, the layout, design, or appearance of the user interface (frontend) will not be considered in the evaluation. We are interested in aspects such as:

* Correct implementation of business logic
* Code structure and organization
* Functionality implemented according to requirements
* Appropriate use of technologies and good backend development practices
* Code performance and efficiency
