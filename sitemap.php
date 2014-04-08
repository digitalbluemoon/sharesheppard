<?php
	if(PHP_SAPI != 'cli')
		die('forbidden');

	require_once __DIR__ . '/core/vendor/autoload.php';
	require_once __DIR__ . '/core/includes/bootstrap.inc';

	drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

	define('SITEMAP_DEST', __DIR__ . '/sitemap.xml');

	$query = db_select('node', 'n');
	$query->join('node_field_data', 'd', 'n.vid = d.vid');
	$query->fields('n', array('nid'));
	$query->fields('d', array('title', 'type', 'created', 'changed'));
	$query->condition('d.status', 1);
	$query->condition('n.type', array('business', 'article', 'page'), 'in');
	$nodes = $query->execute();

	$urls = array();

	$weights = array(
		'business' => '0.5',
		'article' => '0.8',
		'page' => '0.9'
	);

	$last_update = 0;
	while($row = $nodes->fetch()){
		$node_updated = max($row->created, $row->changed);

		if($last_update < $node_updated)
			$last_update = $node_update;

		$urls[] = array(
			'url' => $base_url . url('node/' . $row->nid),
			'modified' => $node_updated,
			'freq' => 'monthly',
			'priority' => $weights[$row->type]
		);
	}

	$nodes->closeCursor();

	$submit = true;
	if(file_exists(SITEMAP_DEST) && preg_match('/<!-- last modified: (\d+)/', file_get_contents(SITEMAP_DST), $matches) && $last_update <= $matches[1])
		$submit = false;

	if(make_sitemap(SITEMAP_DEST, $urls, $last_update) !== false && $submit)
		submit_sitemap(SITEMAP_DEST);

	function make_sitemap($path, $urls, $last_update){
		ob_start();
		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<?xml-stylesheet type="text/xsl" href="xml-sitemap.xsl"?>';
		echo '<!-- last-modified: ', h($last_update), ' -->';
		echo '<urlset
				xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
				xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"
				xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
				http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd"
				xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
				xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"
				xmlns:geo="http://www.google.com/geo/schemas/sitemap/1.0"
				xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"
				xmlns:mobile="http://www.google.com/schemas/sitemap-mobile/1.0"
				xmlns:pagemap="http://www.google.com/schemas/sitemap-pagemap/1.0"
				xmlns:xhtml="http://www.w3.org/1999/xhtml">';

		foreach($urls as $url){
			echo '<url>';
			echo '<loc>', h($url['url']), '</loc>';
			echo '<lastmod>', date('Y-m-d\\TG:i:sP', $url['modified']), '</lastmod>';
			echo '<changefreq>', h($url['freq']), '</changefreq>';
			echo '<priority>', h($url['priority']), '</priority>';
			echo '</url>';
		}

		echo '</urlset>';

		return file_put_contents($path, ob_get_clean());
	}

	function submit_sitemap($path){
		global $base_url;
		$curl = curl_init('http://www.google.com/webmasters/sitemaps/ping?sitemap=' . urlencode($base_url . '/sitemap.xml'));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
		curl_exec($curl);
		curl_close($curl);
	}

	function h($x){
		return htmlspecialchars($x);
	}
