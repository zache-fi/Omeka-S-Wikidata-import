<?php

function get_pori_wd_items() {
   $ret=array();
   $url="https://query.wikidata.org/sparql?format=json&query=%23items%20from%20tm%20collection%0ASELECT%20%3Fitem%20%3FitemLabel%20%3Fesiintym__kohteesta%20%3Fesiintym__kohteestaLabel%20%3Ftekij_%20%3Ftekij_Label%20%3Fimage%20WHERE%20%7B%0A%20%20%3Fitem%20wdt%3AP195%20wd%3AQ86443703.%0A%20%20%3Fitem%20wdt%3AP18%20%3Fimage%20.%0A%20%20SERVICE%20wikibase%3Alabel%20%7B%20bd%3AserviceParam%20wikibase%3Alanguage%20%22en%2Cen%22.%20%7D%0A%20%20OPTIONAL%20%7B%20%3Fitem%20wdt%3AP31%20%3Fesiintym__kohteesta.%20%7D%0A%20%20OPTIONAL%20%7B%20%3Fitem%20wdt%3AP170%20%3Ftekij_.%20%7D%0A%7D";
   $file=curl_get_contents($url);
   $json=json_decode($file, true);

   foreach ($json["results"] as $bindings) {
      foreach ($bindings as $item) {
         $wd_item=str_replace("http://www.wikidata.org/entity/", "", $item["item"]["value"]);
         array_push($ret, $wd_item);
      }
   }

   $ret=array_unique($ret);
   return $ret;
}

function curl_get_contents($url) {
   $ch=curl_init();
   curl_setopt($ch, CURLOPT_URL, $url);
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
   curl_setopt($ch, CURLOPT_USERAGENT, 'Your application name');
   $ret = curl_exec($ch);
   curl_close($ch);
   return $ret;
}


function curl_omekas_post($url, $payload, $method="POST") {
   $login=array(
     "key_identity" => "",
     "key_credential" => ""
   );

   // API URL
   if (!preg_match("|[?]|", $url)) $url.="?";
   foreach($login as $k=>$v)
   {
      $url.="&" . $k ."=" .urlencode($v);
   }
   // Create a new cURL resource
   $ch = curl_init($url);

   // Attach encoded JSON string to the POST fields
   curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

   // Set the content type to application/json

   if ($method=="UPLOAD") {
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data')); 
   } 
   else
   {
      curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
   }

   // Return response instead of outputting
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

   if ($method == "PUT") {
      print("\nPUT\n");

      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
   }
   elseif ($method == "PATCH")
   {

      print("\nPATCH\n");
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
   }

   // Execute the POST request
   $result = curl_exec($ch);

   // Close cURL resource
   curl_close($ch);
   return $result;
}

// Parse  Omeka-S properties
function get_omeka_properties() {
   $properties_url="http://localhost/omeka-s/api/properties";
   $file=file_get_contents($properties_url);
   $json=json_decode($file,true);
   $properties=array();
   $properties_uri=array();
   foreach($json as $k=>$v)
   {
      $properties[$v["o:term"]]=$v;
      $properties_uri[$v["@id"]]=$v;
   }
   return array($properties, $properties_uri);
}

function parse_omeka_wikidata_template() {
   $template_url="http://localhost/omeka-s/api/resource_templates/2";
   $file=file_get_contents($template_url);
   $template=json_decode($file, true);

   $template_props=array();
   foreach  ($template["o:resource_template_property"] as $k=>$v)
   {
      if (preg_match("/\b(P[0-9]+|QID)\b/ism", $v["o:alternate_comment"], $m))
      {
         $p=$v;
         $p["wd_prop"]=$m[1];
         $prop_uri=$p["o:property"]["@id"];
         $prop_data=get_property_from_uri($prop_uri);

         $p["o:term"]=$prop_data["o:term"];
         if (trim($p["o:term"])=="") {
            print_r($prop_data);
         }
         array_push($template_props, $p);
      }
   }
   return array($template, $template_props);
}

function get_wd_item($qid="Q92375789") {
   $url="https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&ids=" . $qid;
   $file=file_get_contents($url);
   $json=json_decode($file, true);
   return $json["entities"][$qid];
}

function get_wd_label($qid, $language) {
   $url="https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=labels&languages=fi&ids=" . $qid;
   $file=file_get_contents($url);
   $json=json_decode($file, true);
   if (isset($json["entities"][$qid]["labels"][$language])) {
      $ret=$json["entities"][$qid]["labels"][$language]["value"];
   }
   else
   {
      $ret=$qid;
   }
   return $ret;
}

function get_item($item_id) {
   $url="http://localhost/omeka-s/api/items/" . $item_id;
   $file=file_get_contents($url);
   $json=json_decode($file, true);
   return $json;
}

function format_quantity($value) {
   $ret=($value["amount"]*1);
   if ($value["unit"]=="http://www.wikidata.org/entity/Q174728") $ret.=" cm";
   return $ret;
}


function set_key_value($item_id, $property, $value, $type) {
   global $omeka_properties;
   $t=array("item"=>$item_id, "property"=>$property, "value"=>$value, "type"=>$type);
   print(json_encode($t));

   if (!isset($omeka_properties[$property])) die("Property not found: $property");

   $data=get_item($item_id);
   if (!isset($data[$property])) {
      $data[$property]=array();
   }

   if (!is_array($value)) $value=array($value);
   
   foreach($value as $v)
   {
      $newvalue=1;
      foreach ($data[$property] as $oldvalue)
      {
         if (isset($oldvalue["@value"]) && $oldvalue["@value"]==$v) $newvalue=0;
         if ($type == 'wikibase-entityid') {
            $testvalue="http://www.wikidata.org/entity/" . $v["id"];
            if (isset($oldvalue["@id"]) && $oldvalue["@id"]==$testvalue) $newvalue=0;

         }
         if ($type == 'uri') {
            if (isset($oldvalue["@id"]) && $oldvalue["@id"]==$v["value"]) $newvalue=0;
         }
         if ($type == 'time') {
            if (isset($oldvalue["@value"]) && $oldvalue["@value"]==$v[0]["value"]) $newvalue=0;
         }
         if ($type == 'quantity') {
            $testvalue=format_quantity($v);
            if (isset($oldvalue["@value"]) && $oldvalue["@value"]==$testvalue) $newvalue=0;
         }
      }

      if ($newvalue) {
         $a=array(
            "type" => $type,
            "property_id" => $omeka_properties[$property]["o:id"],
            "property_label" => $omeka_properties[$property]["o:label"],
            "is_public" => true,
         );
         if ($type == 'literal') {
            $a["@value"]=$v;
         }
         elseif ($type == 'quantity') {
            $a["type"]="literal";
            $a["@value"]=format_quantity($v);
         }
         elseif ($type == 'wikibase-entityid') {
            $a["type"]='uri';
            $a["@id"]="http://www.wikidata.org/entity/" . $v["id"];
            $a['o:label']=get_wd_label($v["id"], "fi");
         }
         elseif ($type == 'uri') {
            $a["@id"]=$v["value"];
            $a['o:label']=$v["label"];
         }
         else
         {
            die("Unknown value");
         }
         array_push($data[$property], $a);
      }
    }

  $r=array();
  if ($newvalue) {
     print("FOO: " . $property ."\n");
     $url="http://localhost/omeka-s/api/items/" . $item_id;
     $r=curl_omekas_post($url, json_encode($data), "PATCH");
     return json_decode($r, true);
  }
  return $r;
}

function upload_file($item_id, $filename, $url) {
   $sourcefile = file_get_contents($url);
   $file_name_with_full_path="/tmp/" . str_replace(" ", "_", trim($filename));

   file_put_contents($file_name_with_full_path, $sourcefile);
   $cFile = curl_file_create($file_name_with_full_path);

   $data = array(
      "o:ingester"=> "upload",
      "file_index"=> "0",
      "o:item"=> array("o:id"=> $item_id),
      "dcterms:title"=> array(
         "type"=> "literal",
         "property_id"=> 1,
         "property_label"=> "Title",
         "@value"=> $filename
      ),
      "dcterms:source"=> array(
         "type"=> "uri",
         "property_id"=> 11,
         "property_label"=> "Source",
         "@id"=> "https://upload.wikimedia.org/wikipedia/commons/e/e9/Alexander_Laureus_Lautta%2C_jossa_on_lukuisia_matkustajia_ja_karjaa_1808.jpg",
         "o:label"=> $filename
      ),
    );
    $r=curl_omekas_post("http://localhost/omeka-s/api/media", array('data'=>json_encode($data), 'file[0]'=>$cFile), "UPLOAD");
    print_r($r);
    return $r;
}

function create_item($item_class, $resource_class_id, $resource_template_id, $title) {
   $data = array(
     "@type"=> array("o:Item", $item_class),
     "o:resource_class"=>array(
        "o:id"=> $resource_class_id,
        "@id"=> 'http://localhost/omeka-s/api/resource_classes/' . $resource_class_id
     ),
     "o:resource_template"=>array(
        "o:id"=>$resource_template_id,
        "@id"=>"http://localhost/omeka-s/api/resource_templates/" . $resource_template_id
     ),
     "o:title"=>$title
   );
   $url="http://localhost/omeka-s/api/items";   


   $r=curl_omekas_post($url, json_encode($data));
   return json_decode($r, true);
}

function get_property_from_uri($uri)
{
   global $omeka_properties, $omeka_properties_uri;
   if (!isset($omeka_properties_uri[$uri])) {
      $file=File_get_contents($uri);
      $json=json_decode($file, true);
      $omeka_properties[$json["o:term"]]=$json;
      $omeka_properties_uri[$uri]=$json;
   }
   return $omeka_properties_uri[$uri];
}

function get_or_create_omeka_item($qid) {
   global $omeka_template_props;
   $url="http://localhost/omeka-s/api/items?property%5B0%5D%5Bproperty%5D=121&property%5B0%5D%5Btype%5D=in&property%5B0%5D%5Btext%5D=" . $qid;
   $file=file_get_contents($url);
   $json=json_decode($file, true);
   
   if (count($json)>1) {
      die("ERROR: Too many items found. Only 1 expected.");
   }
   elseif (isset($json[0])) {
      $item=$json[0];
   }
   else {
      $item=create_item("dctype:StillImage", 33, 2, "New item: " . $qid);
      $uri_value=array(array(
        'value' =>  "http://www.wikidata.org/entity/" . $qid,
        'label' => $qid
      ));

      foreach ($omeka_template_props as $tp) {
         if ($tp["wd_prop"]=="QID") {
            $wikidata_item_property=$tp["o:term"];
         }
      }

      $prop=set_key_value($item["o:id"], $wikidata_item_property, $uri_value, "uri");
   }
   return $item;
}

function handle_wikidata_item($qid) {
   global $wikidata_item_property, $omeka_template, $omeka_template_props, $omeka_properties_uri, $omeka_properties;

   $wd_item=get_wd_item($qid);
   $wd_fi_label=$wd_item["labels"]["fi"]["value"];
   $wd_fi_description=$wd_item["descriptions"]["fi"]["value"];

   print("$qid\t" . $wd_fi_label ."\t" . "\n");
   sleep(10);


   $title_property=$omeka_properties_uri[$omeka_template["o:title_property"]["@id"]]["o:term"];
   $description_property=$omeka_properties_uri[$omeka_template["o:description_property"]["@id"]]["o:term"];

   $item=get_or_create_omeka_item($qid);
   $prop=set_key_value($item["o:id"], $title_property, $wd_fi_label, "literal");
   $prop=set_key_value($item["o:id"], $description_property, $wd_fi_description, "literal");

   $imagefiles=array();
   foreach ($omeka_template_props as $tp) {
      if (isset($wd_item["claims"]) && isset($wd_item["claims"][$tp["wd_prop"]]))
      {
         print_r($tp);
         $datavalues=array();
         $snak_values=array();
         foreach ($wd_item["claims"][$tp["wd_prop"]] as $wd_snak)
         {
            if ($tp["o:term"]=="schema:downloadUrl") {
               $snak_type="uri";
               $snak_value=array(
                   'value' => "https://commons.wikimedia.org/wiki/File:" . str_replace(" ", "_", trim($wd_snak["mainsnak"]["datavalue"]["value"])),
                   'label' => $wd_snak["mainsnak"]["datavalue"]["value"]
               );
               array_push($snak_values, $snak_value);
               array_push($imagefiles, $snak_value);
            }
            else
            {
               array_push($snak_values, $wd_snak["mainsnak"]["datavalue"]["value"]);
               $snak_type=$wd_snak["mainsnak"]["datavalue"]["type"];
            }
         }
         if ($snak_type=="string") {
            $prop=set_key_value($item["o:id"], $tp["o:term"], $snak_values, "literal");
         }
         elseif ($snak_type=="uri") {
            $prop=set_key_value($item["o:id"], $tp["o:term"], $snak_values, "uri");
         }
//         elseif ($snak_type=="time") {
//            $prop=set_key_value($item["o:id"], $tp["o:term"], $snak_values, "time");
//         }
         elseif ($snak_type=="quantity") {
            $prop=set_key_value($item["o:id"], $tp["o:term"], $snak_values, "quantity");
         }
         elseif ($snak_type=="wikibase-entityid")
         {
            $prop=set_key_value($item["o:id"], $tp["o:term"], $snak_values, "wikibase-entityid");
         }
         else
         {
            print_r($snak_type);
//            print_r($snak_values);
//            die();
         }
      }
   }
   foreach($imagefiles as $image)
   {
      print_r($image);
      $url="https://commons.wikimedia.org/w/api.php?action=query&format=json&prop=imageinfo&iiprop=url&titles=file:" . urlencode(str_replace(" ", "_", trim($image['label'])));
      $file=file_get_contents($url);
      $json=json_decode($file, true);
      if (isset($json["query"]) && isset($json["query"]["pages"])) {
         foreach($json["query"]["pages"] as $p) {
            print_r($p);
            $imageurl=$p["imageinfo"][0]["url"];
            upload_file($item["o:id"], $image['label'], $imageurl);
         }
      }
   }
}

list($omeka_template, $omeka_template_props)= parse_omeka_wikidata_template();
list($omeka_properties, $omeka_properties_uri)=get_omeka_properties();

$wikidata_items=get_pori_wd_items();

foreach ($wikidata_items as $qid) {
   $r=handle_wikidata_item($qid);
}

?>


