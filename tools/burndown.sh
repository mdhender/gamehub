#!/usr/bin/env bash
echo "error: script does not work without an API key!"
exit 2

set -euo pipefail

for task in F10; do
  echo "==> Starting ${task}"

  # the -p flag starts an independent session that reads the project configuration
  claude -p \
    "Read docs/SETUP_REPORT.md and BURNDOWN.md for full context. Then implement task ${task} from BURNDOWN.md exactly as specified. Run the test suite and pint. If anything fails, fix it before proceeding. Only when both pass, update BURNDOWN.md and commit with message '${task}: complete'."

  # let the filesystem settle down...
  sleep

  # don't take the agent's word for tests or formatting...
  if ! php artisan test --compact; then
    echo "!!! Task ${task} failed test verification — aborting"
    exit 1
  fi

  if ! ./vendor/bin/pint --test; then
    echo "!!! Task ${task} failed pint verification — aborting"
    exit 1
  fi

  echo "==> ${task} verified"
done

echo "==> All tasks complete"
