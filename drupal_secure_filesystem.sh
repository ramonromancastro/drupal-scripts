#!/bin/bash

# drupal_secure_filesystem.sh secure drupal filesystem installation.
#
# Copyright (C) 2019  Ramón Román Castro <ramonromancastro@gmail.com>
# 
# This program is free software; you can redistribute it and/or
# modify it under the terms of the GNU General Public License
# as published by the Free Software Foundation; either version 2
# of the License, or (at your option) any later version.
# 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
# 
# You should have received a copy of the GNU General Public License
# along with this program; if not, write to the Free Software
# Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.

# ORIGINAL
#  fix-permissions.sh (https://www.drupal.org/node/244924)
# MODIFICADO
#  Ramón Román Castro <ramon.roman.c@juntadeandalucia.es>
# REVISIONES
#  1.0	2016/03/29	Versión original.
#  1.1	2016/03/29	Modificar permisos de .htaccess, settings.php, comprobación de existencia del grupo y modificación del grupo por defecto a apache.
#  1.2	2016/03/29	Mensajes informativos, acceso a las carpetas sin utilizar el comando cd.
#  1.3	2016/03/29	Modificar permisos de .htaccess, settings.php (http://drupal.stackexchange.com/questions/373/what-are-the-recommended-directory-permissions).
#  1.4	2017/03/02	Mensajes identificados con colores para identificarlos mejor.
#  1.5	2017/03/03	Arreglado bug en la detección de la versión de Drupal.
#  1.6	2018/05/07	Cambios menores.
#  1.7	2019/04/23	Cambio de parámetros y valores por defecto.
#  1.8	2019/10/15	Cambios estéticos y modificaciones en la detección de los directorios files. Detección de Drupal 8.
#  1.9	2020/03/09	Cambios de permisos en settings.php y vendor/*.
#  1.10	2020/03/10	Arreglado problema con cambios de permisos en vendor/*.
#  1.11	2026/05/25	Optimización de find
#  1.12	2026/08/14	Modificar permisos de files
#  1.13	2026/11/19	Refactorización y soporte para Drupal 9+

VERSION=1.13

# Constants
declare -A colors=( [debug]="\e[36m" [info]="\e[39m" [ok]="\e[32m" [warning]="\e[93m" [error]="\e[91m" )

# Functions
check_error() {
  if [ $? -gt 0 ]; then
    echo -e " ... ${colors[error]}error\e[0m"
  else
    echo -e " ... ${colors[ok]}ok\e[0m"
  fi
}

print_msg() {
	msg_color=$1
	msg_text=$2
	echo -en "${colors[$msg_color]}${msg_text}\e[0m"
}

print_help() {
cat <<-HELP

Script         : $0
Versión        : ${VERSION}
Original Author: Drupal.org (https://www.drupal.org/node/244924)
Modified by    : Ramón Román Castro <ramonromancastro@gmail.com>

This script is used to fix permissions of a Drupal installation
you need to provide the following arguments:

  1) Path to your Drupal installation [default: Current path].
  2) Username of the user that you want to give files/directories ownership.
  3) HTTPD group name (defaults to apache for Apache).

Usage: (sudo) bash ${0##*/} --path=PATH --user=USER --group=GROUP
Example: (sudo) bash ${0##*/} --path=/usr/local/apache2/htdocs --user=ramon --group=apache

HELP
exit 0
}

if [ $(id -u) != 0 ]; then
  print_msg "warning" "You must run this with sudo or root.\n"
  print_help
  exit 1
fi

DRUPAL_PATH=$(pwd)
DRUPAL_WEBROOT=$(pwd)
USER=
GROUP=

# Parse Command Line Arguments
while [ "$#" -gt 0 ]; do
  case "$1" in
    --path=*)
        DRUPAL_PATH="${1#*=}"
        ;;
    --user=*)
        USER="${1#*=}"
        ;;
    --group=*)
        GROUP="${1#*=}"
        ;;
    --help) print_help;;
    *)
	  print_msg "warning" "Invalid argument, run --help for valid arguments.\n"
      exit 1
  esac
  shift
done

if [ -z "${USER}" ] || [[ $(id -un "${USER}" 2> /dev/null) != "${USER}" ]]; then
  print_msg "warning" "Please provide a valid user.\n"
  print_help
  exit 1
fi

if [ ! $(getent group "${GROUP}") ]; then
  print_msg "warning" "Please provide a valid group.\n"
  print_help
  exit 1
fi

if [ -d "${DRUPAL_PATH}/web" ]; then
    DRUPAL_WEBROOT="${DRUPAL_PATH}/web"
fi

detected=$(grep -oP "^\s*define\('VERSION',\s*'\K.+?(?=\.)" "${DRUPAL_WEBROOT}/includes/bootstrap.inc" 2> /dev/null)
detected=${detected:-N/A}
if [ "${detected}" == "N/A" ]; then
	detected=$(grep -oP "^\s*const VERSION\s*=\s*'\K.+?(?=\.)" "${DRUPAL_WEBROOT}/core/lib/Drupal.php" 2> /dev/null)
	detected=${detected:-N/A}
fi
if [ "${detected}" == "N/A" ]; then
	print_msg "warning" "Drupal installation not detected.\n"
	exit 1
fi

print_msg "debug" "Drupal major version detected: ${detected}\n"
if [[ ! "$detected" =~ ^(7|8|9|10|11)$ ]]; then
	print_msg "warning" "Drupal major version ${detected} not supported by this script.\n"
	exit 1
fi

# Normalize optional glob results and avoid literal unmatched patterns.
shopt -s nullglob
FILES_DIRS=("$DRUPAL_WEBROOT"/sites/*/files)
SETTINGS_FILES=("$DRUPAL_WEBROOT"/sites/*/settings*.php)
SETTINGS_YML_FILES=("$DRUPAL_WEBROOT"/sites/*/settings*.yml)
HTACCESS_FILES=()
while IFS= read -r -d '' f; do HTACCESS_FILES+=("$f"); done < <(find "$DRUPAL_WEBROOT" -type f -iname .htaccess -print0)

#
# Ownership: assign non-root owner and web server group
#

print_msg "info" "Changing ownership of all contents to ${USER}:${GROUP}"
find "$DRUPAL_PATH" \( ! -user "$USER" -o ! -group "$GROUP" \) -exec chown "$USER:$GROUP" -- '{}' +
check_error

#
# Base permissions: Strict principle of least privilege across the entire tree
#

print_msg "info" "Stripping SUID/SGID bits across the entire tree"
find "${DRUPAL_PATH}" \( -perm -4000 -o -perm -2000 \) -exec chmod u-s,g-s -- '{}' +
check_error

print_msg "info" "Setting baseline permissions (0750 dirs)"
find "${DRUPAL_PATH}" -type d ! -perm 0750 -exec chmod 0750 -- '{}' +
check_error
print_msg "info" "Setting baseline permissions (0640 files)"
find "${DRUPAL_PATH}" -type f ! -perm 0640 -exec chmod 0640 -- '{}' +
check_error

#
# vendor/bin permissions
#

print_msg "info" "Setting ${DRUPAL_PATH}/vendor/bin files (0700 files)"
find "${DRUPAL_PATH}/vendor/bin" -type f ! -perm 0700 -exec chmod 0700 -- '{}' +
check_error

#
# files permissions
#

for DIR in "${FILES_DIRS[@]}"; do
    [[ -d "$DIR" ]] || continue
    print_msg "info" "Setting ${DIR} permissions (2770 dirs)"
    find "$DIR" -type d ! -perm 2770 -exec chmod 2770 -- {} +
    check_error
    print_msg "info" "Setting ${DIR} permissions (0660 files)"
    find "$DIR" -type f ! -perm 0660 -exec chmod 0660 -- {} +
    check_error
done

#
# Settings and .htaccess permissions
#

for f in "${SETTINGS_FILES[@]}"; do
    if [[ -f "$f" ]]; then
        print_msg "info" "Setting ${f} permissions (0440 files)"
        chmod 0440 -- "$f"
        check_error
    fi
done

for f in "${SETTINGS_YML_FILES[@]}"; do
    if [[ -f "$f" ]]; then
        print_msg "info" "Setting ${f} permissions (0440 files)"
        chmod 0440 -- "$f"
        check_error
    fi
done

for f in "${HTACCESS_FILES[@]}"; do
    if [[ -f "$f" ]]; then
        print_msg "info" "Setting ${f} permissions (0440 files)"
        chmod 0440 -- "$f"
        check_error
    fi
  chmod 0440 -- "$f"
done

#
# SELinux
#

if command -v getenforce >/dev/null 2>&1 && [[ $(getenforce) != Disabled ]]; then
    if command -v semanage >/dev/null 2>&1 && command -v restorecon >/dev/null 2>&1; then
        for dir in "${FILES_DIRS[@]}"; do
            [[ -d "$dir" ]] || continue
            rule="$dir(/.*)?"
            if ! semanage fcontext -l | grep -F -- "$rule" >/dev/null 2>&1; then
                print_msg "info" "Setting ${dir} SELinux fcontext (httpd_sys_rw_content_t)"
                semanage fcontext -a -t httpd_sys_rw_content_t "$rule"
                check_error
            fi
            print_msg "info" "Restoring ${dir} SELinux fcontext"
            restorecon -R -- "$dir"
            check_error
        done
    elif command -v chcon >/dev/null 2>&1; then
        for dir in "${FILES_DIRS[@]}"; do
            [[ -d "$dir" ]] || continue
            print_msg "info" "Setting ${dir} SELinux context (httpd_sys_rw_content_t)"
            chcon -R -t httpd_sys_rw_content_t "$dir"
            check_error
        done
    else
        print_msg warning 'SELinux is enabled but semanage/restorecon/chcon is unavailable; no labels changed.\n'
    fi
fi

print_msg "warning" "\n=====================================================================\n"
print_msg "warning" "ATTENTION - SECURITY ACTION REQUIRED:\n"
print_msg "warning" "Ensure PHP/CGI execution is disabled in every writable files directory.\n"
print_msg "warning" "=====================================================================\n"

print_msg "ok" "\nDone setting proper permissions on files and directories\n"
