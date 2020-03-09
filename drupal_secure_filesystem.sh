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

VERSION=1.9

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

drupal_path=$(pwd)
drupal_user=apache
httpd_group=apache

# Parse Command Line Arguments
while [ "$#" -gt 0 ]; do
  case "$1" in
    --path=*)
        drupal_path="${1#*=}"
        ;;
    --user=*)
        drupal_user="${1#*=}"
        ;;
    --group=*)
        httpd_group="${1#*=}"
        ;;
    --help) print_help;;
    *)
	  print_msg "warning" "Invalid argument, run --help for valid arguments.\n"
      exit 1
  esac
  shift
done

# if [ -z "${drupal_path}" ] || [ ! -d "${drupal_path}/sites" ] || [ ! -f "${drupal_path}/core/modules/system/system.module" ] && [ ! -f "${drupal_path}/modules/system/system.module" ]; then
  # print_msg "warning" "Please provide a valid Drupal path.\n"
  # print_help
  # exit 1
# fi

if [ -z "${drupal_user}" ] || [[ $(id -un "${drupal_user}" 2> /dev/null) != "${drupal_user}" ]]; then
  print_msg "warning" "Please provide a valid user.\n"
  print_help
  exit 1
fi

if [ ! $(getent group "${httpd_group}") ]; then
  print_msg "warning" "Please provide a valid group.\n"
  print_help
  exit 1
fi

detected=$(grep -oP "^\s*define\('VERSION',\s*'\K.+?(?=\.)" "${drupal_path}/includes/bootstrap.inc" 2> /dev/null)
detected=${detected:-N/A}
if [ "${detected}" == "N/A" ]; then
	detected=$(grep -oP "^\s*const VERSION\s*=\s*'\K.+?(?=\.)" "${drupal_path}/core/lib/Drupal.php" 2> /dev/null)
	detected=${detected:-N/A}
fi
if [ "${detected}" == "N/A" ]; then
	print_msg "warning" "Drupal installation not detected.\n"
	exit 1
fi

print_msg "debug" "Drupal major version detected: ${detected}\n"
if [ "${detected}" != "7" ] && [ "${detected}" != "8" ]; then
	print_msg "warning" "Drupal major version ${detected} not supported by this script.\n"
	exit 1
fi

print_msg "info" "Changing ownership of all contents to ${drupal_user}:${httpd_group}"
chown -R ${drupal_user}:${httpd_group} ${drupal_path}
check_error

print_msg "info" "Changing permissions of all directories to rwxr-x---"
find ${drupal_path} -type d -exec chmod u=rwx,g=rx,o= '{}' \;
check_error

print_msg "info" "Changing permissions of all files to rw-r----- (except ./vendor/* files)"
find ${drupal_path} -path "./vendor" -prune -o -type f -exec chmod u=rw,g=rw,o= '{}' \;
check_error

print_msg "info" "Removing others access to ./vendor files"
find ${drupal_path} -type f -path "./vendor/*" -prune -exec chmod o= '{}' \;
check_error

print_msg "info" "Changing permissions of [files] directories in [sites] to rwxrwx---"
#find ${drupal_path}/sites -type d -name files -exec chmod ug=rwx,o= '{}' \;
find ${drupal_path}/sites/*/ -maxdepth 1 -type d -name files -exec chmod ug=rwx,o= '{}' \;
check_error

print_msg "info" "Changing permissions of [settings.php] files in [sites] to r--r-----"
find ${drupal_path}/sites -type f -name settings.php -exec chmod u=r,g=r,o= '{}' \;
check_error

#print_msg "info" "Changing permissions of all files inside all [files] directories in sites to rw-rw----"
#print_msg "info" "Changing permissions of all directories inside all [files] directories in sites to rwxrwx---"
for x in ${drupal_path}/sites/*/files; do
  print_msg "info" "Changing permissions of all directories in [${x/$drupal_path/}] to rwxrwx---"
  find ${x} -type d -exec chmod ug=rwx,o= '{}' \;
  check_error
  print_msg "info" "Changing permissions of all files in [${x/$drupal_path/}] to rw-rw----"
  find ${x} -type f -exec chmod ug=rw,o= '{}' \;
  check_error
done

print_msg "info" "Changing permissions of [.htaccess] files to r--r-----"
find ${drupal_path} -type f -name .htaccess -exec chmod u=rw,g=r,o= '{}' \;
check_error

print_msg "info" "Changing SELinux context of all directories"
restorecon -R ${drupal_path}
check_error

print_msg "info" "\nDone setting proper permissions on files and directories\n"
