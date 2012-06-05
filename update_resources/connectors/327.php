<?php
namespace php_active_record;
/* connector for Flora of Zimbabwe
estimated execution time: 10 minutes
Partner provides 4 EOL resource XML files. The connector just combines all and generates the final resource file.
*/

include_once(dirname(__FILE__) . "/../../config/environment.php");
$timestart = time_elapsed();
$resource_id = 327;

$files = array();
$files[] = "http://zimbabweflora.co.zw/speciesdata/utilities/eol_families.xml";
$files[] = "http://zimbabweflora.co.zw/speciesdata/utilities/eol_genera.xml";
$files[] = "http://zimbabweflora.co.zw/speciesdata/utilities/eol_species1.xml";
$files[] = "http://zimbabweflora.co.zw/speciesdata/utilities/eol_species2.xml";

combine_remote_eol_resource_files($resource_id, $files);
Functions::set_resource_status_to_force_harvest($resource_id);
$elapsed_time_sec = time_elapsed() - $timestart;
echo "\n";
echo "elapsed time = $elapsed_time_sec seconds             \n";
echo "elapsed time = " . $elapsed_time_sec/60 . " minutes  \n";
exit("\n\n Done processing.");

function combine_remote_eol_resource_files($resource_id, $files)
{
    print "\n\n Start compiling all XML...";
    $OUT = fopen(CONTENT_RESOURCE_LOCAL_PATH . $resource_id . ".xml", "w");
    $str = "<?xml version='1.0' encoding='utf-8' ?>\n";
    $str .= "<response\n";
    $str .= "  xmlns='http://www.eol.org/transfer/content/0.3'\n";
    $str .= "  xmlns:xsd='http://www.w3.org/2001/XMLSchema'\n";
    $str .= "  xmlns:dc='http://purl.org/dc/elements/1.1/'\n";
    $str .= "  xmlns:dcterms='http://purl.org/dc/terms/'\n";
    $str .= "  xmlns:geo='http://www.w3.org/2003/01/geo/wgs84_pos#'\n";
    $str .= "  xmlns:dwc='http://rs.tdwg.org/dwc/dwcore/'\n";
    $str .= "  xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'\n";
    $str .= "  xsi:schemaLocation='http://www.eol.org/transfer/content/0.3 http://services.eol.org/schema/content_0_3.xsd'>\n";
    fwrite($OUT, $str);
    foreach($files as $filename)
    {
        print "\n $filename ";
        $contents = Functions::get_remote_file($filename, DOWNLOAD_WAIT_TIME, 0);
        if($contents != "")
        {
            $pos1 = stripos($contents, "<taxon>");
            $pos2 = stripos($contents, "</response>");
            $str  = substr($contents, $pos1, $pos2-$pos1);
            if($pos1) fwrite($OUT, $str);
        }
        else print "\n no contents [$filename]";
    }
    fwrite($OUT, "</response>");
    fclose($OUT);
    print"\n All XML compiled\n\n";
}

?>