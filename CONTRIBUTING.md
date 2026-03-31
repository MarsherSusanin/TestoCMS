# Contributing

## Before You Start

- Open an issue for substantial changes before starting implementation.
- Keep pull requests scoped to one problem or feature.
- Do not include unrelated refactors in the same change set.

## Local Setup

```bash
composer install
npm ci
cp .env.example .env
php artisan key:generate
```

Use either:

- local PHP setup via `php artisan serve`
- local Docker setup via [docs/docker.md](docs/docker.md)

## Verification

Run the standard checks before opening a pull request:

```bash
composer lint
composer test
npm run build
```

## Pull Request Expectations

- explain the user-facing or operational impact
- list validation steps
- include screenshots for admin or public UI changes
- mention migration, cache, queue, or deployment implications

## Security

Please do not file public issues for vulnerabilities. Follow [SECURITY.md](SECURITY.md).

For private or commercial inquiries outside normal GitHub issue flow, use [me@ilindberg.ru](mailto:me@ilindberg.ru).
