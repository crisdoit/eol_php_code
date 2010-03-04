<?php

class FlickrAPI
{
    public static function get_all_eol_photos($auth_token = "")
    {
        $all_taxa = array();
        $used_image_ids = array();
        $per_page = 100;
        
        // Get metadata about the EOL Flickr pool
        $response = self::pools_get_photos(FLICKR_EOL_GROUP_ID, "", 1, 1, $auth_token);
        if($response)
        {
            $total = $response->photos["total"];
            
            // number of API calls to be made
            $total_pages = ceil($total / 100);
            
            $taxa = array();
            for($i=1 ; $i<=$total_pages ; $i++)
            {
                Functions::display("getting page $i");
                $page_taxa = self::get_eol_photos($per_page, $i, $auth_token);
                
                if($page_taxa)
                {
                    foreach($page_taxa as $t) $all_taxa[] = $t;
                }
            }
        }
        
        return $all_taxa;
    }
    
    public static function get_eol_photos($per_page, $page, $auth_token = "")
    {
        global $used_image_ids;
        
        $response = self::pools_get_photos(FLICKR_EOL_GROUP_ID, "", $per_page, $page, $auth_token);
        
        $page_taxa = array();
        foreach($response->photos->photo as $photo)
        {
            if(@$used_image_ids[(string) $photo["id"]]) continue;
            
            $taxa = self::get_taxa_for_photo($photo["id"], $photo["secret"], $auth_token);
            if($taxa)
            {
                foreach($taxa as $t) $page_taxa[] = $t;
            }
            
            $used_image_ids[(string) $photo["id"]] = true;
        }
        
        return $page_taxa;
    }
    
    public static function get_taxa_for_photo($photo_id, $secret, $auth_token = "")
    {
        $photo_response = self::photos_get_info($photo_id, $secret, $auth_token);
        $photo = $photo_response->photo;
        if(!$photo) Functions::debug("\n\nERROR:Photo $photo_id is not available\n\n");
        
        if($photo->visibility["ispublic"] != 1) return false;
        if($photo->usage["candownload"] != 1) return false;
        
        if(@!$GLOBALS["flickr_licenses"][(string) $photo["license"]]) return false;
        
        $parameters = array();
        $parameters["subspecies"] = array();
        $parameters["trinomial"] = array();
        $parameters["species"] = array();
        $parameters["scientificName"] = array();
        $parameters["genus"] = array();
        $parameters["family"] = array();
        $parameters["order"] = array();
        $parameters["class"] = array();
        $parameters["phylum"] = array();
        $parameters["kingdom"] = array();
        foreach($photo->tags->tag as $tag)
        {
            $string = trim((string) $tag["raw"]);
            
            if(preg_match("/^taxonomy:subspecies=(.*)$/i", $string, $arr)) $parameters["subspecies"][] = strtolower(trim($arr[1]));
            elseif(preg_match("/^taxonomy:trinomial=(.*)$/i", $string, $arr)) $parameters["trinomial"][] = ucfirst(trim($arr[1]));
            elseif(preg_match("/^taxonomy:species=(.*)$/i", $string, $arr)) $parameters["species"][] = strtolower(trim($arr[1]));
            elseif(preg_match("/^taxonomy:binomial=(.*)$/i", $string, $arr)) $parameters["scientificName"][] = ucfirst(trim($arr[1]));
            elseif(preg_match("/^taxonomy:genus=(.*)$/i", $string, $arr)) $parameters["genus"][] = ucfirst(trim($arr[1]));
            elseif(preg_match("/^taxonomy:family=(.*)$/i", $string, $arr)) $parameters["family"][] = ucfirst(trim($arr[1]));
            elseif(preg_match("/^taxonomy:order=(.*)$/i", $string, $arr)) $parameters["order"][] = ucfirst(trim($arr[1]));
            elseif(preg_match("/^taxonomy:class=(.*)$/i", $string, $arr)) $parameters["class"][] = ucfirst(trim($arr[1]));
            elseif(preg_match("/^taxonomy:phylum=(.*)$/i", $string, $arr)) $parameters["phylum"][] = ucfirst(trim($arr[1]));
            elseif(preg_match("/^taxonomy:kingdom=(.*)$/i", $string, $arr)) $parameters["kingdom"][] = ucfirst(trim($arr[1]));
            elseif(preg_match("/^taxonomy:common=(.*)$/i", $string, $arr)) $parameters["commonNames"][] = new SchemaCommonName(array("name" => trim($arr[1])));
        }
        
        $taxon_parameters = array();
        $return_false = false;
        foreach($parameters as $key => $value)
        {
            if(count($value) > 1)
            {
                // there can be more than one common name
                if($key == "commonNames") continue;
                // if there is more than one scientific name disregard all other parameters
                elseif($key == "scientificName")
                {
                    foreach($value as $name)
                    {
                        $taxon_parameters[] = array("scientificName" => $name);
                    }
                }else $return_false = true;
            }
        }
        // return false if there were multiple rank values, and not multiple scientificNames
        if($return_false && !$taxon_parameters) return false;
        
        // if there weren't two scientific names it will get here
        if(!$taxon_parameters)
        {
            $temp_params = array();
            foreach($parameters as $key => $value)
            {
                if($key == "commonNames") $temp_params[$key] = $value;
                elseif($value) $temp_params[$key] = $value[0];
            }
            
            if(@$temp_params["trinomial"]) $temp_params["scientificName"] = $temp_params["trinomial"];
            if(@!$temp_params["scientificName"] && @$temp_params["genus"] && @$temp_params["species"] && !preg_match("/ /", $temp_params["genus"]) && !preg_match("/ /", $temp_params["species"])) $temp_params["scientificName"] = $temp_params["genus"]." ".$temp_params["species"];
            if(@!$temp_params["genus"] && @preg_match("/^([^ ]+) /", $temp_params["scientificName"], $arr)) $temp_params["genus"] = $arr[1];
            if(@!$temp_params["scientificName"] && @!$temp_params["genus"] && @!$temp_params["family"] && @!$temp_params["order"] && @!$temp_params["class"] && @!$temp_params["phylum"] && @!$temp_params["kingdom"]) return false;
            
            $taxon_parameters[] = $temp_params;
        }
        
        // get the data objects and add them to the parameter arrays
        $data_objects = self::get_data_objects($photo);
        if($data_objects)
        {
            foreach($taxon_parameters as &$p)
            {
                $p["dataObjects"] = $data_objects;
            }
        }else return false;
        
        // turn the parameter arrays into objects to return
        $taxa = array();
        foreach($taxon_parameters as &$p)
        {
            $taxa[] = new SchemaTaxon($p);
        }
        
        return $taxa;
    }
    
    public static function get_data_objects($photo)
    {
        $data_objects = array();
        
        $data_object_parameters = array();
        $data_object_parameters["identifier"] = (string) $photo["id"];
        $data_object_parameters["dataType"] = "http://purl.org/dc/dcmitype/StillImage";
        $data_object_parameters["mimeType"] = "image/jpeg";
        $data_object_parameters["title"] = (string) $photo->title;
        $data_object_parameters["description"] = (string) $photo->description;
        $data_object_parameters["mediaURL"] = self::photo_url($photo["id"], $photo["secret"], $photo["server"], $photo["farm"]);
        $data_object_parameters["license"] = @$GLOBALS["flickr_licenses"][(string) $photo["license"]];
        if($photo->dates["taken"]) $data_object_parameters["created"] = (string) $photo->dates["taken"];
        
        foreach($photo->urls->url as $url)
        {
            if($url["type"]=="photopage") $data_object_parameters["source"] = (string) $url;
        }
        
        $agent_parameters = array();
        if(trim($photo->owner["realname"]) != "") $agent_parameters["fullName"] = (string) $photo->owner["realname"];
        else $agent_parameters["fullName"] = (string) $photo->owner["username"];
        $agent_parameters["homepage"] = "http://www.flickr.com/photos/".$photo->owner["nsid"];
        $agent_parameters["role"] = "photographer";
        
        $data_object_parameters["agents"] = array();
        $data_object_parameters["agents"][] = new SchemaAgent($agent_parameters);
        
        if($photo->geoperms["ispublic"] = 1)
        {
            $geo_point_parameters = array();
            if((string) $photo->location["latitude"]) $geo_point_parameters["latitude"] = (string) $photo->location["latitude"];
            if((string) $photo->location["longitude"]) $geo_point_parameters["longitude"] = (string) $photo->location["longitude"];
            if($geo_point_parameters) $data_object_parameters["point"] = new SchemaPoint($geo_point_parameters);
            
            $locations = array();
            if($photo->location->locality) $locations[0] = (string) $photo->location->locality;
            if($photo->location->region) $locations[1] = (string) $photo->location->region;
            if($photo->location->country) $locations[2] = (string) $photo->location->country;
            
            if($locations) $data_object_parameters["location"] = implode(", ", $locations);
        }
        
        $data_objects[] = new SchemaDataObject($data_object_parameters);
        
        
        // If the media type is video, there should be a Video Player type. Add that as a second data object
        if($photo["media"] == "video")
        {
            Functions::debug("getting sizes for id: ".$photo["id"]."\n");
            $sizes = self::photos_get_sizes($photo["id"]);
            if(@$sizes)
            {
                foreach($sizes->sizes->size as $size)
                {
                    if($size["label"] == "Video Player")
                    {
                        $data_object_parameters["identifier"] .= "_video";
                        $data_object_parameters["dataType"] = "http://purl.org/dc/dcmitype/MovingImage";
                        $data_object_parameters["mimeType"] = "video/x-flv";
                        $data_object_parameters["mediaURL"] = $size["source"];
                        
                        $data_objects[] = new SchemaDataObject($data_object_parameters);
                    }
                }
            }
        }
        
        return $data_objects;
    }
    
    public static function photos_get_sizes($photo_id, $auth_token = "")
    {
        $url = self::generate_rest_url("flickr.photos.getSizes", array("photo_id" => $photo_id, "auth_token" => $auth_token), 1);
        return Functions::get_hashed_response($url);
    }
    
    public static function people_get_info($user_id, $auth_token = "")
    {
        $url = self::generate_rest_url("flickr.people.getInfo", array("user_id" => $user_id, "auth_token" => $auth_token), 1);
        return Functions::get_hashed_response($url);
    }
    
    public static function people_get_public_photos($user_id, $auth_token = "")
    {
        $url = self::generate_rest_url("flickr.people.getPublicPhotos", array("user_id" => $user_id, "auth_token" => $auth_token), 1);
        return Functions::get_hashed_response($url);
    }
    
    public static function photos_get_info($photo_id, $secret, $auth_token = "")
    {
        $url = self::generate_rest_url("flickr.photos.getInfo", array("photo_id" => $photo_id, "secret" => $secret, "auth_token" => $auth_token), 1);
        return Functions::get_hashed_response($url);
    }
    
    public static function pools_get_photos($group_id, $machine_tag, $per_page, $page, $auth_token = "", $user_id = NULL)
    {
        $extras = "";
        $url = self::generate_rest_url("flickr.groups.pools.getPhotos", array("group_id" => $group_id, "machine_tags" => $machine_tag, "extras" => $extras, "per_page" => $per_page, "page" => $page, "auth_token" => $auth_token, "user_id" => $user_id), 1);
        return Functions::get_hashed_response($url);
    }
    
    public static function auth_get_frob()
    {
        $url = self::generate_rest_url("flickr.auth.getFrob", array(), 1);
        return Functions::get_hashed_response($url);
    }
    
    public static function auth_check_token($auth_token)
    {
        $url = self::generate_rest_url("flickr.auth.checkToken", array("auth_token" => $auth_token), 1);
        return Functions::get_hashed_response($url);
    }
    
    public static function auth_get_token($frob)
    {
        $url = self::generate_rest_url("flickr.auth.getToken", array("frob" => $frob), 1);
        return Functions::get_hashed_response($url);
    }
    
    public static function photo_url($photo_id, $secret, $server, $farm)
    {
        $photo_url = "http://farm".$farm.".static.flickr.com/".$server."/".$photo_id."_".$secret.".jpg";
        
        // Functions::debug("getting sizes for id: $photo_id\n");
        // $sizes = self::photos_get_sizes($photo_id);
        // if(@$sizes)
        // {
        //     foreach($sizes->sizes->size as $size)
        //     {
        //         $photo_url = $size['source'];
        //     }
        // }
        
        return $photo_url;
    }
    
    public static function valid_auth_token($auth_token)
    {
        $response = self::auth_check_token($auth_token);
        if(@$response->auth->token) return true;
        return false;
    }
    
    public static function login_url()
    {
        $parameters = self::request_parameters(false);
        $parameters["perms"] = "write";
        
        $encoded_parameters = self::encode_parameters($parameters);
        
        return FLICKR_AUTH_PREFIX . implode("&", $encoded_parameters) . "&api_sig=" . self::generate_signature($parameters);
    }
    
    public static function generate_rest_url($method, $params, $sign)
    {
        $parameters = self::request_parameters($method);
        
        foreach($params as $k => $v) $parameters[$k] = $v;
        
        $encoded_paramameters = self::encode_parameters($parameters);
        
        $url = FLICKR_REST_PREFIX.implode("&", $encoded_paramameters);
        
        if($sign) $url.="&api_sig=".self::generate_signature($parameters);
        
        return $url;
    }
    
    public static function encode_parameters($parameters)
    {
        $encoded_paramameters = array();
        foreach($parameters as $k => $v) $encoded_paramameters[] = urlencode($k).'='.urlencode($v);
        return $encoded_paramameters;
    }
    
    public static function request_parameters($method)
    {
        $parameters = array("api_key" => FLICKR_API_KEY);
        if($method) $parameters["method"] = $method;
        
        return $parameters;
    }
    
    public static function generate_signature($parameters)
    {
        $signature = FLICKR_SHARED_SECRET;
        
        ksort($parameters);
        foreach($parameters as $k => $v)
        {
            $signature .= $k.$v;
        }
        
        return md5($signature);
    }
}

?>