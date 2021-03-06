<?php
namespace php_active_record;
/* connector for BOLDS images
estimated execution time:  3 minutes
Provider provides a big text file.
*/

include_once(dirname(__FILE__) . "/../../config/environment.php");
require_library('connectors/BoldsImagesAPI');
$timestart = time_elapsed();

$resource_id = 329;
BoldsImagesAPI::get_all_taxa($resource_id);

//--------------
/* set rating to 2 */
require_library('ResourceDataObjectElementsSetting');
$resource_path = CONTENT_RESOURCE_LOCAL_PATH . $resource_id . ".xml";
$bolds = new ResourceDataObjectElementsSetting($resource_id, $resource_path, 'http://purl.org/dc/dcmitype/StillImage', 2);
$xml = $bolds->set_data_object_rating_on_xml_document();
$bolds->save_resource_document($xml);
//--------------

Functions::set_resource_status_to_harvest_requested($resource_id);

$elapsed_time_sec = time_elapsed() - $timestart;
echo "\n";
echo "elapsed time = $elapsed_time_sec seconds             \n";
echo "elapsed time = " . $elapsed_time_sec/60 . " minutes  \n";
echo "elapsed time = " . $elapsed_time_sec/60/60 . " hours \n";
echo "\n\n Done processing.";
?>