# One backend pattern

Status: accepted.

## Context

A backend that starts with a stock layout grows a second layout the first time a domain needs a folder the stock one has no name for. Controllers, validation and authorization each drift into two spellings within a few pull requests, because people and agents copy the code next to the code they change. A green pipeline does not stop it: coverage and static analysis check lines, not structure.

The starter exists so that the end state of the zero-debt kit is the first commit: every task has one way, written down, with a guard that fails the other way and a counter that is at zero from the start.

## Decision

Domain code goes in `app/Modules/<Domain>`, with a fixed set of folders and a naming rule per folder. The framework layer stays flat like the stock framework: single-action controllers under `Http/Controllers/<Area>/<Noun>`, form requests, middleware, models, policies and providers. The folder table and the one way to do each common task are in `app/AGENTS.md` and in the one-pattern table of `AGENTS.md`.

Spatie `Data` classes are the DTOs of the API: a controller returns one, never an array and never an API resource. An action changes state through one `handle()`; a service holds the reads several callers share. Authorization is `can:` on the route, a rate limit is `throttle:<limiter>` on the route, and the model, URL, date and password strictness switches are calls in one provider.

The rules are enforced three ways: PHPStan at level 10 with the disallowed-calls rules, architecture tests of class shape and layout, and the ratchet rows of `scripts/ci-ratchets.sh`, which are at zero and take no override. A row that is at zero stays a row: a list that comes back fails the build.

## Consequences

A new domain is a new folder of `app/Modules`, and a class outside the folders of the table fails `tests/Unit/ModuleLayoutArchitectureTest.php`. A pattern that changes is a pull request that changes the table, the guard and the example file together. There is no migration of old code, because there is no old code; a project that adopts the starter replaces the notes module with its own domain and keeps the rules.
