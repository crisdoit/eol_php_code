<?php

include_once(dirname(__FILE__) . "/../config/environment.php");
require_vendor('namelink');
$GLOBALS['ENV_DEBUG'] = false;
ob_implicit_flush();
@ob_end_flush();

$start_id = @$argv[1];
$end_id = @$argv[2];

if(!$start_id && !$end_id)
{
    echo "tag_data_objects.php \$start_id \$end_id\n\n";
    exit;
}


$nametag = new NameTag('');
if($end_id) $result = $GLOBALS['db_connection']->query("SELECT id, description FROM data_objects WHERE description!='' AND (visibility_id=".Visibility::find('preview')." OR (visibility_id=".Visibility::find('visible')." AND published=1)) AND id BETWEEN $start_id AND $end_id");
else $result = $GLOBALS['db_connection']->query("SELECT id, description FROM data_objects WHERE id=$start_id");

$i = 0;
$GLOBALS['db_connection']->begin_transaction();
while($result && $row=$result->fetch_assoc())
{
    if($i%50 == 0)
    {
        echo "$i: ".time_elapsed()."\n";
        flush();
        $GLOBALS['db_connection']->commit();
    }
    $i++;
    
    $id = $row['id'];
    $description = $row['description'];
    
    $nametag->reset($description);
    $description = $nametag->markup_html();
    
    $description = NameLink::replace_tags_with_collection($description, 'EOLLookup::sdaf');
    
    echo "UPDATE data_objects SET description_linked='".$GLOBALS['db_connection']->real_escape_string($description)."' WHERE id=$id\n";
    $GLOBALS['db_connection']->query("UPDATE data_objects SET description_linked='".$GLOBALS['db_connection']->real_escape_string($description)."' WHERE id=$id");
}
$GLOBALS['db_connection']->end_transaction();





class EOLLookup
{
    public static function sdaf($name_string)
    {
        $name_string = trim($name_string);
        if(!$name_string) return false;
        $canonical_form = Functions::canonical_form($name_string);
        
        $json = array();
        $result = $GLOBALS['db_connection']->query("SELECT tc.id FROM canonical_forms cf JOIN names n ON (cf.id=n.canonical_form_id) JOIN taxon_concept_names tcn ON (n.id=tcn.name_id) JOIN taxon_concepts tc ON (tcn.taxon_concept_id=tc.id) WHERE cf.string='$canonical_form' AND tcn.preferred=1 AND tcn.source_hierarchy_entry_id!=0 AND tc.published=1 AND tc.vetted_id=5 AND tc.supercedure_id=0 ORDER BY tc.id ASC LIMIT 1");
        if($result && $row=$result->fetch_assoc())
        {
            $json[] = array('url' => '/pages/'. $row['id']);
        }
        
        return $json;
    }
}
