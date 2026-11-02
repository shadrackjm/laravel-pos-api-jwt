# POS API with Laravel 13 + JWT — course source code

The source code for the YouTube course **Build a POS API in Laravel 13 with JWT Auth**. It's a
Point of Sale REST API: products and categories, staff who log in with JWTs, a checkout that
records multi-item sales, and the hard parts most courses skip. Those include money in integer
cents, two cashiers racing for the last item, the duplicate checkout, refunds and IDOR.

**Every episode has a git tag.** `ep05` is the code exactly as it stands at the end of
episode 5, so you can compare your code with ours at any point, or jump in mid-series.

## Getting the code for an episode

```bash
git clone https://github.com/shadrackjm/laravel-pos-api-jwt.git pos
cd pos

git checkout ep11      # the end of episode 11, which is where episode 12 starts
```

> **The off-by-one:** to *follow* episode 12, start from `ep11`. To *compare* your finished
> episode 12, look at `ep12`. `git diff ep11 ep12` shows exactly what episode 12 changed.

The `main` branch is the finished app (the same code as `ep24`).

## Running it

You need PHP 8.3+ (8.4 recommended), Composer and MySQL. The course uses
[Laravel Herd](https://herd.laravel.com), but any PHP + MySQL setup works.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret          # from episode 10 onward
```

Set the database in `.env`. **Use MySQL, not SQLite:** `lockForUpdate()` is silently ignored on
SQLite, so the race-condition fix in episode 17 can't work there.

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pos
DB_USERNAME=root
DB_PASSWORD=
```

Then build and seed the database:

```bash
php artisan migrate:fresh --seed
```

From episode 14 the seeder creates two staff accounts. Both use the password `password`:

| Email | Role |
|---|---|
| `manager@pos.test` | manager |
| `jane@pos.test` | cashier |

Run the tests (from episode 12 onward):

```bash
php artisan test
```

The API lives under `/api/v1`, for example `http://pos.test/api/v1/ping` on Herd. From episode 23
there's a [Bruno](https://www.usebruno.com) collection in `bruno/`: open the folder in Bruno, pick
the **Local** environment and run the requests in order.

## Episodes and tags

| Tag | Episode | What the code has at the end of it |
|---|---|---|
| `ep00` | — | a fresh Laravel 13 project with Pest |
| `ep01` | 1. Introduction | nothing yet (same as `ep00`) |
| `ep02` | 2. API routing & your first endpoint | `routes/api.php`, `/api/v1/ping` returning the envelope |
| `ep03` | 3. Designing the POS database | migrations for categories, products, customers, sales, sale items |
| `ep04` | 4. Models & relationships | models with `#[Fillable]`, casts and relationships |
| `ep05` | 5. Products & categories CRUD | API controllers and routes |
| `ep06` | 6. API Resources & pagination | `ProductResource`, `UserResource` |
| `ep07` | 7. One envelope & error handling | `ApiController`, Form Requests, central exception handling |
| `ep08` | 8. Why APIs need tokens | no code (same as `ep07`) |
| `ep09` | 9. What is a JWT? | no code (same as `ep07`) |
| `ep10` | 10. Register & login | the role column, `jwt:secret`, `TokenService`, register and login |
| `ep11` | 11. Protecting the API | the `jwt.auth` middleware, protected routes |
| `ep12` | 12. Refresh tokens & logout | refresh-token rotation, the denylist, real logout, Pest setup + `AuthTest` |
| `ep13` | 13. The checkout | `CheckoutService` in a transaction, sales endpoints, uuid route keys |
| `ep14` | 14. Roles & permissions | `EnsureUserHasRole`, 401 vs 403, reads split from writes |
| `ep15` | 15. Ownership & IDOR | every sales query scoped to its owner |
| `ep16` | 16. Money done right | integer cents throughout, `pos:money` |
| `ep17` | 17. Race conditions | `lockForUpdate`, sorted locks, deadlock retry, `pos:race` |
| `ep18` | 18. Idempotency | the `Idempotency-Key` middleware on checkout |
| `ep19` | 19. Side effects | receipts sent after the transaction commits |
| `ep20` | 20. Refunds | refunds that restore stock and can't run twice |
| `ep21` | 21. Search, filters & sorting | validated, whitelisted query parameters |
| `ep22` | 22. Reports & performance | the sales report, `preventLazyLoading`, `pos:nplusone` |
| `ep23` | 23. Testing | the Bruno collection (the Pest suite is complete: 39 tests) |
| `ep24` | 24. Shipping | CORS, Scribe API docs, production config |

## The demo commands

Some episodes come with a command you can run yourself:

```bash
php artisan pos:money            # episode 16: why floats can't hold money
php artisan pos:race --unsafe    # episode 17: two cashiers, one last mug (stock goes to -1)
php artisan pos:race             # episode 17: the same race with the fix (stock stays 0)
php artisan pos:nplusone         # episode 22: the N+1 query problem, measured
```

## Stack

Laravel 13 · PHP 8.4 · `firebase/php-jwt` 7 · MySQL · Pest · Bruno · Scribe
