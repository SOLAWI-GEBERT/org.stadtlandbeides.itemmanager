---
name: run-remote-test
description: Sync local code to the remote CiviCRM buildkit container and run PHPUnit tests. Displays results and optionally iterates on failures.
argument-hint: [--testsuite <name> | --filter <method> | <file-path>]
allowed-tools: Read, Bash, Edit, Write, Glob, Grep
---

Run PHPUnit tests on the remote CiviCRM container for: $ARGUMENTS

## Remote Environment

- **Host**: `192.168.4.35` (QNAP NAS)
- **SSH user**: `nasmaster`
- **Container runtime**: LXC via Container Station
- **Container name**: `civiBot`
- **LXC binary**: `/share/CACHEDEV1_DATA/.qpkg/container-station/bin/lxc`
- **Extension path inside container**: `/root/.openclaw/workspace/org.stadtlandbeides.itemmanager`
- **PHPUnit config**: `phpunit.xml.dist`

## Connection Variables

```bash
SSH_HOST="nasmaster@192.168.4.35"
LXC="/share/CACHEDEV1_DATA/.qpkg/container-station/bin/lxc"
CONTAINER="civiBot"
REMOTE_DIR="/root/.openclaw/workspace/org.stadtlandbeides.itemmanager"
PATH_EXPORT="export PATH=/root/.composer/vendor/bin:/root/civicrm-buildkit/bin:\$PATH"
```

## Workflow

### 1. Sync local changes to remote

Copy changed files via SSH + LXC. Use `git diff --name-only HEAD~1` (or a user-specified range) to identify changed files, then transfer each one:

```bash
for FILE in <changed-files>; do
  ssh $SSH_HOST "$LXC exec $CONTAINER -- bash -c 'cat > $REMOTE_DIR/$FILE'" < "$FILE"
done
```

If many files changed or the user says "sync all", use a tar pipe:

```bash
git diff --name-only HEAD~1 | tar cf - -T - | \
  ssh $SSH_HOST "$LXC exec $CONTAINER -- bash -c 'cd $REMOTE_DIR && tar xf -'"
```

### 2. Run tests

Build the phpunit command from `$ARGUMENTS`:

| Argument | PHPUnit flag |
|---|---|
| `--testsuite Util` | `--testsuite Util` |
| `--filter testMethodName` | `--filter testMethodName` |
| `tests/phpunit/suites/util/SomeTest.php` | path argument |
| _(empty)_ | run all tests |

Execute remotely:

```bash
ssh $SSH_HOST "$LXC exec $CONTAINER -- bash -c '$PATH_EXPORT && cd $REMOTE_DIR && phpunit -c phpunit.xml.dist <flags> 2>&1'"
```

Set a timeout of 300000ms (5 minutes) for the SSH command.

### 3. Evaluate results

- Parse the PHPUnit output for `OK`, `FAILURES`, or `ERRORS`.
- On **OK**: Report the summary line (tests, assertions, time).
- On **FAILURES/ERRORS**: Extract each failing test name and error message. Present a concise summary to the user.

### 4. Fix-and-rerun loop (optional)

If the user asks to fix failures:

1. Read the failing test and related production code locally.
2. Apply fixes using Edit tool.
3. Sync only the changed files (step 1).
4. Re-run only the failing tests using `--filter` (step 2).
5. Repeat until green or until the user stops.

Do NOT enter this loop automatically — only when the user explicitly requests fixing.

## Rules

- Always sync before running. Never assume the remote has the latest code.
- Show the full PHPUnit summary output to the user.
- If SSH fails, report the connection error and suggest checking network/SSH config.
- Do not modify remote files directly via SSH editors — always edit locally and sync.
- Keep timeout at 300s for test runs; use 120s for sync operations.
