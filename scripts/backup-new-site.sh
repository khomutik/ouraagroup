#!/usr/bin/env bash
# Pull a consistent, encrypted copy of the production site's files to OneDrive.
# The age identity must remain outside OneDrive and outside this repository.
set -euo pipefail

: "${PN_BACKUP_IDENTITY:?Set the local age identity path}"
: "${PN_BACKUP_DEST:?Set the OneDrive destination directory}"
: "${PN_BACKUP_SSH_KEY:?Set the local SSH private key path}"

identity="$(cygpath -u "$PN_BACKUP_IDENTITY")"
destination="$(cygpath -u "$PN_BACKUP_DEST")"
ssh_key="$(cygpath -u "$PN_BACKUP_SSH_KEY")"
host="${PN_BACKUP_HOST:-root@194.60.132.53}"

[[ -f "$identity" && -f "$ssh_key" ]] || {
  echo 'Backup identity or SSH key is missing.' >&2
  exit 1
}
mkdir -p -- "$destination"

stamp="$(date +%Y-%m-%dT%H%M%S%z)"
archive="$destination/pochtinormalnye-vps-1063655-$stamp.tar.gz.age"
partial="$archive.partial"
[[ ! -e "$archive" && ! -e "$partial" ]] || {
  echo 'Backup name already exists; refusing to overwrite it.' >&2
  exit 1
}
trap 'rm -f -- "$partial"' EXIT

recipient="$(age-keygen -y "$identity")"
ssh -T -i "$ssh_key" -o BatchMode=yes -o ConnectTimeout=15 "$host" \
  'tar --numeric-owner -C / -czf - srv/pochtinormalnye-site etc/caddy/Caddyfile' \
  | age -r "$recipient" -o "$partial"

# Decrypt and validate the gzip/tar stream before publishing the backup file.
age -d -i "$identity" "$partial" | tar -tzf - > /dev/null
mv -- "$partial" "$archive"
trap - EXIT
printf 'Verified encrypted backup: %s\n' "$archive"
