#!/usr/bin/env bash
# Removes files that are not used by the application (template leftovers,
# empty files, old logs, stale build chunks). Review the list, then run:
#
#   bash scripts/cleanup-unused.sh
#
# The release package built by scripts/build-release.sh already excludes these,
# so running this is optional for deployment; it only tidies the repository.
set -euo pipefail
cd "$(dirname "$0")/.."

V=resources/views
paths=(
  # Root leftovers from a "project root as web root" setup, empty files, old logs
  index.php server.php test test.php error_log favicon.ico robots.txt
  nuxt.config.js vite.config.js cmd.sh
  # Stale copy of public/assets (views load /assets from public/)
  assets
  # Public error log and the template's web installer
  public/error_log public/vendor/wizard-installer
  # Old webpack chunk; `npm run prod` regenerates the current one
  public/js/node_modules_agora-rtc-sdk-ng_AgoraRTC_N-production_js.js
  # Views not referenced anywhere
  $V/vendor/installer $V/chat-demo.blade.php $V/chat2.blade.php $V/students/test.blade.php
  $V/welcome.blade.php $V/fees/fees_pending.blade.php $V/online_exam/edit_class_questions.blade.php
  $V/principal/chat.blade.php $V/users/reset_password.blade.php $V/emails/forgot_password.blade.php
  $V/emails/forgot_password_web.blade.php $V/emails/password_reset.blade.php
  $V/auth/register.blade.php $V/auth/verify.blade.php $V/fees/pdf_email.blade.php $V/index.html
  # Empty stub controllers with no routes
  app/Http/Controllers/V2/ForgetPasswordController.php app/Http/Controllers/V2/StudentController.php
  # Local debug output
  storage/debugbar storage/app/excel
)

for p in "${paths[@]}"; do
  if [ -e "$p" ]; then
    rm -rf -- "$p"
    echo "removed $p"
  fi
done
rmdir public/vendor 2>/dev/null || true
echo "Done."
