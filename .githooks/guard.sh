#!/bin/sh
# Refuses Markdown files, secrets and local data in commits and pushes.
# Used by .githooks/pre-commit and .githooks/pre-push (enabled with: git config core.hooksPath .githooks).
# Reads file paths (one per line) on stdin; for "content" mode also scans the staged diff for secret patterns.

blocked_paths='(\.md|\.MD|\.markdown)$|(^|/)\.env($|\.)|(^|/)auth\.json$|\.(key|pem|p12|pfx|crt)$|(^|/)id_rsa|credentials|local-admin|\.sqlite|\.(db|sql|dump)$|(^|/)storage/app/(public|private)/'
allowed_paths='(^|/)\.env\.example$|(^|/)storage/app/(public|private)/\.gitignore$'

bad_files=$(grep -E "$blocked_paths" | grep -Ev "$allowed_paths")
status=0

if [ -n "$bad_files" ]; then
    echo "Blocked: these files must not be committed or pushed (Markdown, secrets or local data):" >&2
    echo "$bad_files" | sed 's/^/  - /' >&2
    status=1
fi

if [ "$1" = "content" ]; then
    # Only file names are printed, never the matching text, so a secret is not echoed into logs.
    secret_pattern='BEGIN [A-Z ]*PRIVATE KEY|APP_KEY=base64:|ghp_[A-Za-z0-9]{20,}|github_pat_[A-Za-z0-9_]{20,}|sk-[A-Za-z0-9]{20,}|AKIA[0-9A-Z]{16}|xox[baprs]-[A-Za-z0-9-]+'
    secret_files=$(git diff --cached --name-only --diff-filter=ACMR -- . ':(exclude).githooks' | while read -r file; do
        if git diff --cached -U0 --no-color -- "$file" | grep -E '^\+' | grep -v '^+++' | grep -qE "$secret_pattern"; then
            echo "$file"
        fi
    done)
    if [ -n "$secret_files" ]; then
        echo "Blocked: these staged files contain what looks like a secret key or token:" >&2
        echo "$secret_files" | sed 's/^/  - /' >&2
        status=1
    fi
fi

if [ "$status" -ne 0 ]; then
    echo "Remove them from the commit (git restore --staged <file>) and keep them local." >&2
fi
exit $status
