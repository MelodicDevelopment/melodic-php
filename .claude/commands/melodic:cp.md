---
name: melodic:cp
description: Commit all changes with a descriptive message and push to remote
---

# Commit and Push

Commit all staged and unstaged changes with a well-written, descriptive commit message, then push to the remote repository.

## Instructions

1. Run `git status` to see all changes (do not use -uall flag)
2. Run `git diff` to understand what was changed
3. Stage all relevant changes with `git add` (prefer specific files over `git add .`)
4. Create a descriptive commit message that:
    - Uses a clear, concise subject line (50 chars or less)
    - Starts with a verb in imperative mood (e.g., "Add", "Fix", "Update", "Refactor")
    - Includes a body explaining the "why" behind the changes if needed
    - References any relevant ticket/issue numbers if mentioned in the conversation
5. Commit the changes using a HEREDOC for proper formatting
6. Push to the remote repository
7. Report the commit hash and confirmation of the push

## Important

- Do NOT include any Co-Authored-By lines
- Do NOT include Claude as a contributor or author
- Do NOT add any AI attribution to the commit
