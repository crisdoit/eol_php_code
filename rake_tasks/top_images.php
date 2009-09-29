<?php

//define('DEBUG', true);
//define('MYSQL_DEBUG', true);
//define('DEBUG_TO_FILE', true);
//define("MYSQL_DEBUG", true);
//define('ENVIRONMENT', 'integration');
include_once(dirname(__FILE__) . "/../config/start.php");

$mysqli =& $GLOBALS['mysqli_connection'];


$result = $mysqli->query("SELECT he.id, he.lft, he.rgt, he.taxon_concept_id, he.hierarchy_id FROM hierarchies_content_test hc JOIN hierarchy_entries he ON (hc.hierarchy_entry_id=he.id) WHERE he.hierarchy_id!=105 AND he.hierarchy_id!=129 AND he.hierarchy_id!=399 AND (hc.image=1 OR hc.child_image=1 OR hc.image_unpublished=1 OR hc.child_image_unpublished=1)");
if(@!$result || @!$result->num_rows) exit;

$image_type_id = DataType::find("http://purl.org/dc/dcmitype/StillImage");

$top_images_data = fopen(LOCAL_ROOT . "temp/top_images.sql", "w+");
$top_unpublished_images_data = fopen(LOCAL_ROOT . "temp/top_unpublished_images.sql", "w+");

$i = 0;
while($result && $row=$result->fetch_assoc())
{
    if($i%100 == 0) echo "$i\n";
    $i++;
    
    $id = $row["id"];
    $lft = $row["lft"];
    $rgt = $row["rgt"];
    $taxon_concept_id = $row["taxon_concept_id"];
    $hierarchy_id = $row["hierarchy_id"];
    
    $top_images = array();
    $top_unpublished_images = array();
    
    if($lft == $rgt)
    {
        $query = "SELECT do.id, do.data_rating, do.visibility_id, do.published FROM hierarchy_entries he STRAIGHT_JOIN taxa t ON (he.id=t.hierarchy_entry_id) STRAIGHT_JOIN data_objects_taxa dot ON (t.id=dot.taxon_id) STRAIGHT_JOIN data_objects do ON (dot.data_object_id=do.id) WHERE he.taxon_concept_id=$taxon_concept_id AND do.data_type_id=$image_type_id";
    }else
    {
        $query = "SELECT do.id, do.data_rating, do.visibility_id, do.published FROM hierarchy_entries he_direct_ancestors STRAIGHT_JOIN hierarchy_entries he_all_ancestors ON (he_direct_ancestors.taxon_concept_id=he_all_ancestors.taxon_concept_id) STRAIGHT_JOIN taxa t ON (he_all_ancestors.id=t.hierarchy_entry_id) STRAIGHT_JOIN data_objects_taxa dot ON (t.id=dot.taxon_id) STRAIGHT_JOIN data_objects do ON (dot.data_object_id=do.id) WHERE he_direct_ancestors.lft BETWEEN $lft AND $rgt AND he_direct_ancestors.rgt!=0 AND he_direct_ancestors.hierarchy_id=$hierarchy_id AND do.data_type_id=$image_type_id";
    }
    $result2 = $mysqli->query($query);
    while($result2 && $row2=$result2->fetch_assoc())
    {
        $data_rating = $row2["data_rating"];
        $data_object_id = $row2["id"];
        $visibility_id = $row2["visibility_id"];
        $published = $row2["published"];
        if($visibility_id==Visibility::find("visible") && $published==1) $top_images[$data_rating][$data_object_id] = "$id\t$data_object_id";
        else $top_unpublished_images[$data_rating][$data_object_id] = "$id\t$data_object_id";
    }
    
    $view_order = 1;
    krsort($top_images);
    foreach($top_images as $k => $v)
    {
        ksort($v);
        foreach($v as $k2 => $v2)
        {
            fwrite($top_images_data, $v2 . "\t$view_order\n");
            $view_order++;
            if($view_order > 500) break;
        }
        if($view_order > 500) break;
    }
    
    
    $view_order = 1;
    ksort($top_unpublished_images);
    foreach($top_unpublished_images as $k => $v)
    {
        ksort($v);
        foreach($v as $k2 => $v2)
        {
            fwrite($top_unpublished_images_data, $v2 . "\t$view_order\n");
            $view_order++;
            if($view_order > 500) break;
        }
        if($view_order > 500) break;
    }
}

fclose($top_images_data);
fclose($top_unpublished_images_data);

// exit if there is no new data
if(!filesize(LOCAL_ROOT ."temp/top_images.sql")) exit;


$mysqli->begin_transaction();

echo "Deleting old data\n";
$mysqli->delete("DELETE FROM top_images");
$mysqli->delete("DELETE FROM top_unpublished_images");


echo "inserting new data\n";
echo "1 of 2\n";
$mysqli->load_data_infile(LOCAL_ROOT ."temp/top_images.sql", "top_images");
echo "2 of 2\n";
$mysqli->load_data_infile(LOCAL_ROOT ."temp/top_unpublished_images.sql", "top_unpublished_images");


echo "removing data files\n";
// shell_exec("rm ". LOCAL_ROOT ."temp/top_images.sql");
// shell_exec("rm ". LOCAL_ROOT ."temp/top_unpublished_images.sql");


echo "Update 1 of 4\n";
$mysqli->update("UPDATE taxon_concept_content_test tcct JOIN hierarchy_entries he USING (taxon_concept_id) JOIN top_images ti ON (he.id=ti.hierarchy_entry_id) SET tcct.child_image=1, tcct.image_object_id=ti.data_object_id WHERE ti.view_order=1");
echo "Update 2 of 4\n";
$mysqli->update("UPDATE taxon_concept_content tcc JOIN hierarchy_entries he USING (taxon_concept_id) JOIN top_images ti ON (he.id=ti.hierarchy_entry_id) SET tcc.child_image=1, tcc.image_object_id=ti.data_object_id WHERE ti.view_order=1");


echo "Update 3 of 4\n";
$mysqli->update("UPDATE hierarchies_content_test hct JOIN top_images ti USING (hierarchy_entry_id) SET hct.child_image=1, hct.image_object_id=ti.data_object_id WHERE ti.view_order=1");
echo "Update 4 of 4\n";
$mysqli->update("UPDATE hierarchies_content hc JOIN top_images ti USING (hierarchy_entry_id) SET hc.child_image=1, hc.image_object_id=ti.data_object_id WHERE ti.view_order=1");

//another one for child_images?

$mysqli->end_transaction();



?>