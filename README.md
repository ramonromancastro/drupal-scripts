# drupal-scripts
Collection of drupal scripts used for maintenance tasks

## Instalación
```sh
$ git clone https://github.com/ramonromancastro/drupal-scripts.git
```
## Scripts

### drupal7.unused.php

**drupal7.unused.php** es un script para eliminar los archivos "huérfanos" y/o en desuso de instalaciones de Drupal Core 7.x. Los archivos que este script revisa, identifica y elimina son:

  - Archivos de la tabla {file_managed} que no se encuentran en el sistema de archivos, tienen tamaño 0 o no son utilizados por Drupal Core.
  - Archivos del sistema de archivos que no están administrados por Drupal Core

#### Requisitos
 - Drupal Core 7.x
 - PHP 5.3 o superior

#### Ejecución
Para ver los comandos disponibles, ejecutar el comando
```sh
$ php /path/to/drupal7.unused.php -h
```
#### Ejemplos
```sh
$ cd /path/to/drupal7/installation
$ php /path/to/drupal7.unused.php -H server.domain.local -s /drupal/context/
```

### drupal_secure_filesystem.sh
Este script se encarga de modificar los permisos de acceso al directorio de instalación de Drupal.

#### Requisitos
 - Drupal Core 7.x, 8.x
 - PHP 5.3 o superior
