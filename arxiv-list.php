<?php
/**
 * @package Arxiv_List
 * @version 0.4.1
 */
/*
Plugin Name: ArXiv list
Plugin URI: https://github.com/robol/arxiv-list
Description: Generate a list of preprints from the ArXiv.
Author: Leonardo Robol
Version: 0.4.1
Author URI: https://leonardo.robol.it/
*/

class Paper {
    public $title = "";
    public $authors = "";
    public $published = 0;
    public $link = "#";
    public $summary = "";
    public $year = 0;
}

function al_get_papers($orcid) {
    $url = "https://arxiv.org/a/" . $orcid . ".atom2";
    $res = file_get_contents($url);

    return al_get_papers_from_xml($res);
}

function al_get_papers_from_xml($res) {
    if (! $res) {
        return [];
    }

    $papers = [];

    try {
        $data = new SimpleXMLElement($res);
        $npapers = count($data->entry);

        for ($i = 0; $i < $npapers; $i++) {
            $p = $data->entry[$i];

            $dt = new DateTime($p->published);

            $paper = new Paper;
            $paper->title = $p->title;
            $paper->authors = $p->author->name;
            $paper->published = $dt->getTimestamp();
            $paper->link = $p->link[0]["href"][0];
            $paper->summary = $p->summary;
            $paper->year = $dt->format('Y');

            array_push($papers, $paper);
        }
    }
    catch (Exception $e) {
        // We do nothing, and silently ignore malformed responses.
    }

    return $papers;
}

function al_get_recent_papers($orcids, $n) {
    $responses = [];
    $requests = [];

    foreach ($orcids as $orcid) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://arxiv.org/a/" . trim($orcid) . ".atom2");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $requests[$orcid] = $ch;
    }

    // Perform the requests in parallel with Curl multi
    $cm = curl_multi_init();
    foreach ($requests as $ch) {
        curl_multi_add_handle($cm, $ch);
    }

    $running = true;
    while ($running) {
        curl_multi_exec($cm, $running);
    }

    // Get all responses
    foreach ($orcids as $orcid) {
        $responses[$orcid] = curl_multi_getcontent($requests[$orcid]);
    }

    $papers = [];
    foreach ($orcids as $orcid) {
        $papers = array_merge($papers, al_get_papers_from_xml($responses[$orcid]));        
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

    // Try to get the result from cache, if possible. If we have it, 
    // avoid making the extra AJAX request from the page, and directly 
    // embed the list. 
    $key = al_compute_cache_key($a['orcids'], $a['npapers']);
    $cached_data = get_transient($key);

    if (false !== $cached_data) {
        return $cached_data;
    }

    return '<p class="al_paper_list" data-orcids="' . 
        $a['orcids'] . '"' . 
        ' data-npapers="' . $a['npapers'] . '">' . 
    'Loading preprints ...</p>';
}

function al_generate_recent_paper_html($a) {
    $key = al_compute_cache_key($a['orcids'], $a['npapers']);

    $orcids = explode(',', $a['orcids']);
    $npapers = $a['npapers'];
    $papers = al_get_recent_papers($orcids, $npapers);
    
    $buffer = "<ul>";
    foreach ($papers as $paper) {
        $buffer = $buffer . "<li>" . 
            '<a href="' . $paper->link . '">' .
                $paper->title . 
            "</a>, " . 
            $paper->authors . ", " .
            $paper->year . ".</li>";
    }
    $buffer = $buffer . "</ul>";

    // Save in cache for the next calls, we store it for 6 hours seconds
    set_transient($key, $buffer, 10 * MINUTES_IN_SECONDS);

    return $buffer;
}

function arxiv_list_api(WP_REST_Request $request) {
    $data = $request->get_params();
    $buffer = al_generate_recent_paper_html($data);

    return new WP_REST_Response([ 'response' => $buffer ], 200);
}

add_shortcode('arxiv_list', 'arxiv_list_shortcode');

add_action('rest_api_init', function() {
    register_rest_route( 'arxiv_list/v1', '/generate', array(
      'methods' => 'POST',
      'callback' => 'arxiv_list_api',
    ) );
  } );

function al_enqueue_script() {   
    wp_enqueue_script( 'arxiv_list', plugin_dir_url( __FILE__ ) . 'arxiv-list.js' );
}
add_action('wp_enqueue_scripts', 'al_enqueue_script');


?>
