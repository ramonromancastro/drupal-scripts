<?php

# drupal7.unused.php is a PHP scripts to delete orphans files in Drupal
# Core 7.x installations.
#
# Copyright (C) 2018 Ramon Roman Castro <ramonromancastro@gmail.com>
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

define("RRC_VERSION","1.33");

# ---------------------------------------------------------------------------------------
# FUNCIONES
# ---------------------------------------------------------------------------------------

$progress_value=0;
$progress_total=0;
$progress_time=null;

$my_stats = Array(
	'file_managed_total' => 0,
	'file_managed_deleted' => 0,
	'null_file_managed_total' => 0,
	'null_file_managed_deleted' => 0,
	'zero_file_managed_total' => 0,
	'zero_file_managed_deleted' => 0,
	'unpublished_file_managed_total' => 0,
	'unpublished_file_managed_deleted' => 0,
	'calculate_file_managed_total' => 0,
	'calculate_file_managed_process' => 0,
	'calculate_file_managed_deleted' => 0,
	'calculate_filesystem_total' => 0,
	'calculate_filesystem_process' => 0,
	'calculate_filesystem_deleted' => 0,
	'free_disk_space' => 0,
);

function rrc_is_multisite(){
	return (file_exists(DRUPAL_ROOT . "/sites/sites.php"));
}

function rrc_print_multisite(){
	if (rrc_is_multisite()){
		require DRUPAL_ROOT . "/sites/sites.php";
		echo "Sites availables:\n\n";
		ksort($sites);
		$keys = array_unique($sites);
		sort($keys);
		echo "  default\n";
		foreach($keys as $key => $value){
			echo "  $value\n";
			foreach($sites as $skey => $svalue){
				if ($svalue == $value){ echo "    - $skey\n"; }
			}
		}
		echo "\n";
	}
}

function rrc_start_progress($total){
	global $progress_value,$progress_total,$progress_time;
	
	$progress_value=1;
	$progress_total=$total;
	$progress_time=microtime(true);
}
function rrc_print_progress(){
	global $options,$progress_value,$progress_total,$progress_time;
	
	if (!isset($options['v'])){
		$time_ahora = microtime(true);
		$time_total = $time_ahora - $progress_time;
		$time_restante = ($time_total / $progress_value) * ($progress_total - $progress_value + 1);
		$time_restante = sprintf("%02d:%02d",$time_restante/60,$time_restante%60);
	
		printf("%6.2f%%  [%6d/%6d] Tiempo estimado: %s\r",($progress_value*100/$progress_total),$progress_value,$progress_total,$time_restante);
	}
	$progress_value++;
}

function rrc_print_copyright(){
	global $options;
	echo "\ndrupal7.delete.unused.php - Delete unused images\n";
	echo "Ramón Román Castro <ramon.roman.c@juntadeandalucia.es>\n";
	echo "Versión ".RRC_VERSION."\n\n";
}

function rrc_print_environment(){
	global $options;

	if (!isset($options['f'])){
		echo "Modo simulación    [\033[01;92m ACTIVADO \033[0m]\n";
	}
	else{
		echo "Modo simulación    [\033[01;93m DESACTIVADO \033[0m]\n";
	}
	if (isset($options['o'])){
		echo "Eliminar huérfanos [\033[01;93m ACTIVADO \033[0m]\n";
	}
	else{
		echo "Eliminar huérfanos [\033[01;93m DESACTIVADO \033[0m]\n";
	}
	if (isset($options['v'])){
		echo "Modo detallado     [\033[01;92m ACTIVADO \033[0m]\n";
	}
	else{
		echo "Modo detallado     [\033[01;90m DESACTIVADO \033[0m]\n";
	}
	if (rrc_is_multisite()){
		echo "Multi-site         [\033[01;93m DETECTADO \033[0m]\n";
	}
	else{
		echo "Multi-site         [\033[01;92m NO DETECTADO \033[0m]\n";
	}
	
	$conf_path = &drupal_static('conf_path', '*** Unknown path ***');
	
	echo "\n";
	if (isset($options['H']))
		echo "HTTP Request  : http://{$options['H']}{$options['s']}\n";
	else
		echo "HTTP Request  : __default__\n";
	echo "Site name     : " . variable_get('site_name', '*** Unknown site ***') . "\n";
	echo "Site config   : " . $conf_path . "\n";
	echo "Public path   : " . variable_get('file_public_path', null) . "\n";
	echo "Private path  : " . variable_get('file_private_path', null) . "\n";
	echo "Temporary path: " . variable_get('file_temporary_path', null) . "\n";
	echo "Default schema: " . variable_get('file_default_scheme', null) . "\n";
	echo "\n";
}

function rrc_verbose($text){
	global $options;
	if (isset($options['v'])) echo "[ \033[01;36mDEBUG\033[0m ] $text\n";
}

function rrc_print_help(){
	rrc_print_copyright();
	echo "Usage: drupal7_unused.php -p <PATH> -H <HTTP_HOST> -s <SCRIPT_NAME> -d <DATE> -f -o -v -h\n\n";
	echo "\t-p <PATH>        ... Ruta base de la instalación de Drupal (por defecto: ruta actual)\n";
	echo "\t-H <HTTP_HOST>   ... Nombre del host en formato FQDN:(PORT) para simular una petición Web\n";
	echo "\t-s <SCRIPT_NAME> ... Ruta de acceso absoluta para simular una petición Web\n";
	echo "\t                     \033[01;93mOBLIGATORIO en caso de Multi-site\033[0m\n";
	echo "\t-d <DATE>        ... Fecha de despublicación. Formato: YYYY-MM-DD\n";
	echo "\t-f               ... Desactivar modo simulación (por defecto): los cambios son definitivos\n";
	echo "\t-o               ... Incluir la eliminación de archivos 'huérfanos' en el sistema de archivos\n";
	echo "\t-v               ... Modo detallado\n";
	echo "\t-h               ... Muestra esta ayuda\n";
	echo "\n";
	rrc_print_multisite();
	exit;
}

function rrc_markEmptyOrNullRecords(){
	global $options, $my_stats;
	
	echo "Marcando los archivos vacíos o nulos en ".Database::getConnection()->prefixTables("{file_managed}")." (by Drupal Core)\n";
	$result = db_query("UPDATE {file_managed} SET status = 0 WHERE uri = '' OR uri IS NULL");
						   
	$my_stats['null_file_managed_total']=$result->rowCount();
	$my_stats['null_file_managed_deleted']=$result->rowCount();
}

function rrc_deleteOrphansRecords(){
	global $options, $my_stats;
	
	echo "Eliminación de archivos 'huérfanos' en ".Database::getConnection()->prefixTables("{file_managed}")." (by Drupal Core)\n";
	$result = db_query("SELECT fm.*".
						" FROM {file_managed} AS fm ".
						" LEFT OUTER JOIN {file_usage} AS fu ON ( fm.fid = fu.fid ) ".
						" LEFT OUTER JOIN {node} AS n ON ( fu.id = n.nid ) ".
						" WHERE (fu.type = 'node' OR fu.type IS NULL) AND n.nid IS NULL AND fm.uri IS NOT NULL AND fm.uri <> '' ".
						" ORDER BY `fm`.`fid`  DESC ");
	rrc_start_progress($result->rowCount());
	foreach ($result as $delta => $record) {
		rrc_print_progress();
		rrc_verbose($record->uri);
		$my_stats['file_managed_total']++;
		if (isset($options['f'])) $result = file_delete($record,true); else $result = FALSE;
		if ($result === TRUE) $my_stats['file_managed_deleted']++;
	}
}

function rrc_deleteZeroRecords(){
	global $options, $my_stats;
	
	echo "Eliminación de archivos de tamaño 0 en ".Database::getConnection()->prefixTables("{file_managed}")." (by Drupal Core)\n";
	$result = db_query("SELECT fm.* FROM {file_managed} fm WHERE fm.filesize = 0 AND filesize = 0 AND (uri like 'public://%' or uri like 'private://%' or uri like 'temporary://%')");
	rrc_start_progress($result->rowCount());
	foreach ($result as $delta => $record) {
		rrc_print_progress();
		rrc_verbose($record->uri);
		$my_stats['zero_file_managed_total']++;
		if (isset($options['f'])) $result = file_delete($record,true); else $result = FALSE;
		if ($result === TRUE) $my_stats['zero_file_managed_deleted']++;
	}
}

function rrc_deleteUnpublishedRecords(){
	global $options, $my_stats;
	
	if (!empty($options['d']) && preg_match('/\d{4}\-\d{2}\-\d{2}/',$options['d'])){
		echo "Eliminación de archivos 'despublicados' en ".Database::getConnection()->prefixTables("{file_managed}")." (by Drupal Core)\n";
		$result = db_query("SELECT " .
							" fm.* " .
							" FROM " .
							" {file_managed} fm, " .
							" {node} nod, " .
							" {field_data_field_fecha_despublicacion} fde, " .
							" {field_data_field_imagen} fim " .
							" WHERE " .
							" nod.nid = fde.entity_id " .
							" AND fde.entity_id = fim.entity_id " .
							" AND nod.nid = fde.entity_id " .
							" AND fim.field_imagen_fid = fm.fid " .
							" AND fde.field_fecha_despublicacion_value <= '".$options['d']."'");
		rrc_start_progress($result->rowCount());
		foreach ($result as $delta => $record) {
			rrc_print_progress();
			rrc_verbose($record->uri);
			if ($wrapper = file_stream_wrapper_get_instance_by_uri($record->uri)) {
				$file_realpath = $wrapper->realpath();
				$file = stat($file_realpath);
				$my_stats['free_disk_space']+=$file['size'];
			}
			$my_stats['unpublished_file_managed_total']++;
			if (isset($options['f'])) $result = file_delete($record,true); else $result = FALSE;
			if ($result === TRUE) $my_stats['unpublished_file_managed_deleted']++;
		}
	}
	else{
		echo "\033[01;93mEliminación de archivos 'despublicados' en ".Database::getConnection()->prefixTables("{file_managed}")." (by Drupal Core)\033[0m\n";
	}
}

function rrc_deleteOrphansAndZeroRecordsByFileSystem(){
	global $options, $my_stats;
	
	echo "Eliminación de archivos 'huérfanos' y de tamaño 0 en ".Database::getConnection()->prefixTables("{file_managed}")." (by Drupal Core & FileSystem)\n";
	$result = db_query("SELECT fm.* FROM {file_managed} fm WHERE uri like 'public://%' OR uri like 'private://%'");
	rrc_start_progress($result->rowCount());
	foreach ($result as $delta => $record) {
		rrc_print_progress();
		if ($wrapper = file_stream_wrapper_get_instance_by_uri($record->uri)) {
			$file_realpath = $wrapper->realpath();
			if (!file_exists($file_realpath)){
				rrc_verbose($record->uri);
				$my_stats['calculate_file_managed_total']++;
				$my_stats['calculate_file_managed_process']++;
				if (isset($options['f'])) $result = file_delete($record,true); else $result = FALSE;
				if ($result === TRUE) $my_stats['calculate_file_managed_deleted']++;
			}
			else{
				$file = stat($file_realpath);
				if (!$file['size']){
					rrc_verbose($record->uri);
					$my_stats['free_disk_space']+=256;
					$my_stats['calculate_file_managed_total']++;
					$my_stats['calculate_file_managed_process']++;
					if (isset($options['f'])) $result = file_delete($record,true); else $result = FALSE;
					if ($result === TRUE) $my_stats['calculate_file_managed_deleted']++;
				}
			}
		}
	}
}

function rrc_deleteOrphansFileSystem(){
	global $options, $my_stats, $my_file_managed;
	
	echo "Eliminación de archivos 'huérfanos' en el sistema de archivos (by FileSystem)\n";

	// Añadimos los directorios de búsqueda de huérfanos
	$context = Array();
	if ($wrapper = file_stream_wrapper_get_instance_by_uri('public://')) {
		$context[$wrapper->realpath()] = 'public:/';
	}
	if ($wrapper = file_stream_wrapper_get_instance_by_uri('private://')) {
		$context[$wrapper->realpath()] = 'private:/';
	}

	// Buscamos los huérfanos en los directorios
	foreach($context as $file_path => $file_schema){
		echo "Path: $file_path\n";
		$file_scan = file_scan_directory($file_path,'/.*/',Array('recurse' => FALSE));
		rrc_start_progress(count($file_scan));
		foreach($file_scan as $key => $value){
			rrc_print_progress();
			if (!is_dir($value->uri)){
				$schema_file = str_replace($file_path,$file_schema,$value->uri);
				$found = false;
				foreach($my_file_managed as $item){
					if (isset($item[$schema_file])){
						$found = true;
						break;
					}
				}
				if (!$found){
					rrc_verbose($value->uri);
					$file = stat($value->uri);
					$my_stats['free_disk_space']+=$file['size'];
					$my_stats['calculate_filesystem_total']++;
					$my_stats['calculate_filesystem_process']++;
					if (isset($options['f']) && isset($options['o'])) $result = file_unmanaged_delete($value->uri); else $result = FALSE;
					if ($result === TRUE) $my_stats['calculate_filesystem_deleted']++;
				}
				// if (!isset($my_file_managed[$schema_file])){
					// rrc_verbose($value->uri);
					// $file = stat($value->uri);
					// $my_stats['free_disk_space']+=$file['size'];
					// $my_stats['calculate_filesystem_total']++;
					// $my_stats['calculate_filesystem_process']++;
					// if (isset($options['f'])) $result = file_unmanaged_delete($value->uri); else $result = FALSE;
					// if ($result === TRUE) $my_stats['calculate_filesystem_deleted']++;
				// }
			}
		}
	}
}

# ---------------------------------------------------------------------------------------
# Lectura de parámetros y preparación para funcionar Drupal Core en línea de comandos
# ---------------------------------------------------------------------------------------

// Extraemos la fecha/hora de la ejecución para no borrar archivos insertados durante la ejecución del script
$date = new DateTime();
$date->sub(new DateInterval('P1D'));
$executionTime = $date->getTimestamp();

$_SERVER['REMOTE_ADDR'] = '';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = gethostname();

$options = getopt("p:H:s:d:fovh");
if (isset($options['h'])){
	rrc_print_help();
}

if (!empty($options['p'])) chdir($options['p']);
define('DRUPAL_ROOT', getcwd());

if (rrc_is_multisite() && (empty($options['H']) || empty($options['s']))){
	echo "\033[01;93mMulti-site detectado!\033[0m\n";
	rrc_print_help();
}

if (!empty($options['H'])) $_SERVER['HTTP_HOST'] = $options['H'];
if (!empty($options['s'])) $_SERVER['SCRIPT_NAME'] = $options['s'];

# ---------------------------------------------------------------------------------------
# Copia del archivo index.php de Drupal Core exceptuando la última línea
# ---------------------------------------------------------------------------------------

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * The routines here dispatch control to the appropriate handler, which then
 * prints the appropriate page.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt.
 */

/**
 * Root directory of Drupal installation.
 */
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

# ---------------------------------------------------------------------------------------
# Cuerpo principal del script
# ---------------------------------------------------------------------------------------

$tiempo_inicio = microtime(true);

$conf['error_level'] = 2;

rrc_print_copyright();
rrc_print_environment();

// Añadimos las conexiones por defecto del multisite a las conexiones actuales
$my_sites = Array();
if (rrc_is_multisite()){
	if (file_exists(DRUPAL_ROOT . "/sites/default/settings.php")){
		echo "Añadiendo la conexión a la base de datos por defecto ...\n";
		require DRUPAL_ROOT . "/sites/default/settings.php";
		Database::addConnectionInfo("____default", 'default', $databases['default']['default']);
		$my_sites[] = '__default';
	}
	require DRUPAL_ROOT . "/sites/sites.php";
	foreach($sites as $site_key => $site_value){
		if (!in_array($site_value,$my_sites)){
			if (file_exists(DRUPAL_ROOT . "/sites/$site_value/settings.php")){
				echo "Añadiendo la conexión a la base de datos del site $site_value ...\n";
				$databases = null;
				require DRUPAL_ROOT . "/sites/$site_value/settings.php";
				Database::addConnectionInfo("__$site_value", 'default', $databases['default']['default']);
				$my_sites[] = $site_value;
			}
		}
	}
}

rrc_markEmptyOrNullRecords();

rrc_deleteOrphansRecords();

rrc_deleteZeroRecords();

rrc_deleteUnpublishedRecords();

rrc_deleteOrphansAndZeroRecordsByFileSystem();

$my_file_managed = Array();
$result = db_query("SELECT uri FROM {file_managed}");
#foreach ($result as $delta => $record) { $my_file_managed[$record->uri] = 1; };
foreach ($result as $delta => $record) { $my_file_managed['__no_multisite'][$record->uri] = 1; };

foreach($my_sites as $site_value){
	db_set_active("__$site_value");
	$result = db_query("SELECT uri FROM {file_managed}");
	#foreach ($result as $delta => $record) { $my_file_managed[$record->uri] = 1; };
	foreach ($result as $delta => $record) { $my_file_managed[$site_value][$record->uri] = 1; };
	db_set_active();
}
rrc_deleteOrphansFileSystem();


$tiempo_fin = microtime(true);

echo "\n================================================================================";
echo "\nRESULTADO [Total / Procesados / Eliminados]";
echo "\n================================================================================\n\n";
printf("Archivos 'nulos' \033[01;93m[1]\033[0m                               : %6d / %6d / \033[01;93m%6d\033[0m\n",$my_stats['null_file_managed_total'], $my_stats['null_file_managed_total'], 0);
printf("Archivos 'huérfanos'                               : %6d / %6d / %6d\n",$my_stats['file_managed_total'], $my_stats['file_managed_total'], $my_stats['file_managed_deleted']);
printf("Archivos de tamaño 0                               : %6d / %6d / %6d\n",$my_stats['zero_file_managed_total'], $my_stats['zero_file_managed_total'], $my_stats['zero_file_managed_deleted']);
printf("Archivos 'despublicados'                           : %6d / %6d / %6d\n",$my_stats['unpublished_file_managed_total'],$my_stats['unpublished_file_managed_total'],$my_stats['unpublished_file_managed_deleted']);
printf("Archivos 'huérfanos' y de tamaño 0                 : %6d / %6d / %6d\n",$my_stats['calculate_file_managed_total'],$my_stats['calculate_file_managed_process'],$my_stats['calculate_file_managed_deleted']);
printf("Archivos 'huérfanos' en el sistema de archivos \033[01;93m[2]\033[0m : %6d / %6d / %6d\n",$my_stats['calculate_filesystem_total'],$my_stats['calculate_filesystem_process'],$my_stats['calculate_filesystem_deleted']);
echo "\n--------------------------------------------------------------------------------\n";
printf("Espacio en disco liberado (Estimado)               : %.2f MiB",$my_stats['free_disk_space']/1024/1024);
echo "\n--------------------------------------------------------------------------------\n";

echo "\033[01;93m";
echo "\nNOTAS\n";
echo "[1] Estos archivos serán eliminados en las posteriores ejecuciones del cron de Drupal Core\n";
if (!isset($options['o']))
	echo "[2] Estos archivos sólo se eliminan con la opción -o activada\n";
echo "\033[0m";


echo "\nTiempo empleado : " . round($tiempo_fin - $tiempo_inicio,2) . " segundos\n\n";
?>
