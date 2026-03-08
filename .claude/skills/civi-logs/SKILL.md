---
name: civi-logs
description: Check PHP and CiviCRM logs on the local MAMP test server. Shows recent errors, filters by time/pattern, and helps diagnose issues.
argument-hint: [<minutes> | <search-pattern> | errors | backtrace | clear]
allowed-tools: Read, Bash, Grep, Glob
user-invocable: true
---

Analyze local test server logs for: $ARGUMENTS

## Log Locations

- **PHP error log**: `/Applications/MAMP/logs/php_error.log`
- **CiviCRM logs**: `/Users/linus/Documents/0_Development/Stadt-Land-Beides/JML3/media/civicrm/ConfigAndLog/` (multiple `.log` files, use the most recent)

## Argument Interpretation

| Argument | Action |
|---|---|
| _(empty)_ | Show last 50 lines from both PHP and CiviCRM logs |
| `<number>` (e.g. `5`) | Show log entries from the last N minutes |
| `<pattern>` (e.g. `Itemmanager`, `SQL`) | Grep both logs for the pattern |
| `errors` | Show only PHP Fatal/Warning/Error and CiviCRM exceptions |
| `backtrace` | Show recent stack traces / backtraces from both logs |
| `clear` | Report file sizes, then ask user for confirmation before truncating |

## Workflow

### 1. Check server is running

```bash
curl -s -o /dev/null -w "%{http_code}" http://localhost:8888/ 2>/dev/null || echo "offline"
```

If offline, inform the user that MAMP does not appear to be running, but still check the log files (they persist).

### 2. Identify the most recent CiviCRM log

```bash
ls -t /Users/linus/Documents/0_Development/Stadt-Land-Beides/JML3/media/civicrm/ConfigAndLog/*.log 2>/dev/null | head -1
```

CiviCRM log files are typically named `CiviCRM.YYYY-MM-DD.log` or similar. Always use the most recently modified file.

### 3. Read and analyze logs

Based on the argument, apply the appropriate action:

**Default (last 50 lines)**:
```bash
tail -50 /Applications/MAMP/logs/php_error.log
tail -50 <most-recent-civicrm-log>
```

**Time-based (last N minutes)**:
Use `perl` or `awk` to filter entries by timestamp within the last N minutes. PHP log format: `[DD-Mon-YYYY HH:MM:SS ...]`. CiviCRM log format varies — check for date patterns.

**Pattern search**:
```bash
grep -i "<pattern>" /Applications/MAMP/logs/php_error.log | tail -30
grep -i "<pattern>" <most-recent-civicrm-log> | tail -30
```

**Errors only**:
```bash
grep -iE "(fatal|error|warning|exception|critical)" /Applications/MAMP/logs/php_error.log | tail -30
grep -iE "(fatal|error|exception|critical|backtrace)" <most-recent-civicrm-log> | tail -30
```

**Backtrace**:
Show lines containing "Stack trace", "Backtrace", "#[0-9]" and surrounding context (grep -A/B).

**Clear**:
Show file sizes with `ls -lh`, then ask user for confirmation before running `> file` to truncate.

### 4. Present results

- Summarize findings concisely: count of errors/warnings, most frequent error, time of last error.
- Group repeated errors and show count instead of duplicating output.
- Highlight lines referencing `itemmanager`, `Itemmanager`, or `org.stadtlandbeides` — these are from our extension.
- If a PHP fatal or CiviCRM exception is found, extract the error message and file:line reference.
- When showing backtraces, identify the originating call from our extension code.

## Rules

- Always check BOTH log sources (PHP + CiviCRM) unless the user specifically asks for only one.
- Limit output — never dump more than 80 lines per log source. Summarize if there are more.
- Do NOT modify or delete log files without explicit user confirmation.
- If the CiviCRM ConfigAndLog directory has multiple log files, mention all of them with their sizes and dates, but only analyze the most recent one by default.
- Timestamps in output should be human-readable. Convert if needed.
