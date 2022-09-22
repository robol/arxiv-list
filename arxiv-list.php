<?php
/**
 * @package Arxiv_List
 * @version 0.10.
 */
/*
Plugin Name: ArXiv list
Plugin URI: https://github.com/robol/arxiv-list
Description: Generate a list of preprints from the ArXiv.
Author: Leonardo Robol
Version: 0.1.0
Author URI: https://leonardo.robol.it/
*/

class Paper {
    public $title = "";
    public $authors = "";
    public $published = 0;
    public $link = "#";
}

function al_get_papers($orcid) {
    $url = "https://arxiv.org/a/" . $orcid . ".atom2";
    $res = file_get_contents($url);

    $data = new SimpleXMLElement($res);
    $npapers = count($data->entry);
    $papers = [];

    for ($i = 0; $i < $npapers; $i++) {
        $p = $data->entry[$i];
        $paper = new Paper;
        $paper->title = $p->title;
        $paper->authors = $p->author->name;
        $paper->published = (new DateTime($p->published))->getTimestamp();
        $paper->link = $p->link[0]["href"][0];

        array_push($papers, $paper);
    }

    return $papers;
}

function al_get_recent_papers($orcids, $n) {
    $papers = [];
    foreach ($orcids as $orcid) {
        $papers = array_merge($papers, al_get_papers($orcid));
    }

    uasort($papers, function ($a, $b) {
        return $a->published < $b->published;
    });

    return array_slice($papers, 0, $n);
}

function al_compute_cache_key($orcids, $n) {
    // Make a uniquer identifier for the specified ORCIDs and 
    // the number of papers. 
    $key = 'arxiv_list:' . hash('md5', $orcids) . ":" . $n;

    return $key;
}

function arxiv_list_shortcode($atts) {
    $a = shortcode_atts(array(
        'orcids' => "",
        'npapers' => 5
    ), $atts);

    // Try to get the result from cache, if possible
    $key = al_compute_cache_key($a['orcids'], $a['npapers']);
    $cached_data = get_transient($key);
    
    if (false !== $cached_data) {
        return $cached_data;
    }

    $orcids = explode(',', $a['orcids']);
    $npapers = $a['npapers'];
    $papers = al_get_recent_papers($orcids, $npapers);
    
    $buffer = "<p><ul>";
    foreach ($papers as $paper) {
        $buffer = $buffer . "<li>" . 
            '<a href="' . $paper->link . '">' .
                $paper->title . 
            "</a>, " . 
            $paper->authors .
            ".</li>";
    }
    $buffer = $buffer . "</ul></p>";

    // Save in cache for the next calls, we store it for 6 hours seconds
    set_transient($key, $buffer, 6 * HOUR_IN_SECONDS);

    return $buffer;
}

add_shortcode('arxiv_list', 'arxiv_list_shortcode');


?>