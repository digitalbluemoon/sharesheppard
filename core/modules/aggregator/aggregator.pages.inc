<?php

/**
 * @file
 * User page callbacks for the Aggregator module.
 */

use Drupal\aggregator\Entity\Feed;
use Drupal\Core\Entity\EntityInterface;

/**
 * Loads and optionally filters feed items.
 *
 * @param string $type
 *   The type of filter for the items. Possible values are:
 *   - sum: No filtering.
 *   - source: Filter the feed items, limiting the result to items from a
 *     single source.
 *   - category: Filter the feed items by category.
 * @param mixed $data
 *   Feed or category data used for filtering. The type and value of $data
 *   depends on $type:
 *   - source: $data is an object with $data->fid identifying the feed used to
 *     as filter.
 *   - category: $data is an array with $data['cid'] being the category id to
 *     filter on.
 *   The $data parameter is not used when $type is 'sum'.
 * @param int $limit
 *   (optional) The number of records to return. Defaults to 20.
 *
 * @deprecated Use \Drupal\aggregator\ItemStorageController::loadAll() for
 *   loading all feed items, \Drupal\aggregator\ItemStorageController::loadByFeed()
 *   for loading feed items filtered by the source feed, and \Drupal\aggregator\ItemStorageController::loadByCategory()
 *   for loading feed items filtered by the feed category.
 *
 * @return \Drupal\aggregator\ItemInterface[]
 *   An array of the feed items.
 */
function aggregator_load_feed_items($type, $data = NULL, $limit = 20) {
  $storage_controller = Drupal::entityManager()->getStorageController('aggregator_item');
  switch ($type) {
    case 'sum':
      return $storage_controller->loadAll($limit);

    case 'source':
      return $storage_controller->loadByFeed($data->id(), $limit);

    case 'category':
      return $storage_controller->loadByCategory($data->cid, $limit);
  }
}

/**
 * Default theme implementation to present a linked feed item for summaries.
 *
 * @param $variables
 *   An associative array containing:
 *   - item_link: Link to item.
 *   - item_age: Age of the item.
 *
 * @see template_preprocess()
 * @see template_preprocess_aggregator_summary_item()
 */
function theme_aggregator_summary_item($variables) {
  return $variables['item_url'] . ' ' . $variables['item_age'];
}

/**
 * Prepares variables for aggregator item templates.
 *
 * Default template: aggregator-item.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - aggregator_item: An individual feed item for display on the aggregator
 *     page.
 */
function template_preprocess_aggregator_item(&$variables) {
  $item = $variables['aggregator_item'];

  $variables['feed_url'] = check_url($item->link->value);
  $variables['feed_title'] = check_plain($item->title->value);
  $variables['content'] = aggregator_filter_xss($item->description->value);

  $variables['source_url'] = '';
  $variables['source_title'] = '';
  if (isset($item->ftitle) && isset($item->fid->value)) {
    $variables['source_url'] = url("aggregator/sources/$item->fid->value");
    $variables['source_title'] = check_plain($item->ftitle);
  }
  if (date('Ymd', $item->timestamp->value) == date('Ymd')) {
    $variables['source_date'] = t('%ago ago', array('%ago' => format_interval(REQUEST_TIME - $item->timestamp->value)));
  }
  else {
    $variables['source_date'] = format_date($item->timestamp->value, 'medium');
  }

  $variables['categories'] = array();
  foreach ($item->categories as $category) {
    $variables['categories'][$category->cid] = l($category->title, 'aggregator/categories/' . $category->cid);
  }

  $variables['attributes']['class'][] = 'feed-item';
}

/**
 * Page callback: Generates an OPML representation of all feeds.
 *
 * @param $cid
 *   (optional) If set, feeds are exported only from a category with this ID.
 *   Otherwise, all feeds are exported. Defaults to NULL.
 *
 * @return string
 *   An OPML formatted string.
 *
 * @see aggregator_menu()
 */
function aggregator_page_opml($cid = NULL) {
  if ($cid) {
    $result = db_query('SELECT f.title, f.url FROM {aggregator_feed} f LEFT JOIN {aggregator_category_feed} c on f.fid = c.fid WHERE c.cid = :cid ORDER BY title', array(':cid' => $cid));
  }
  else {
    $result = db_query('SELECT * FROM {aggregator_feed} ORDER BY title');
  }

  $feeds = $result->fetchAll();
  $aggregator_page_opml = array(
    '#theme' => 'aggregator_page_opml',
    '#feeds' => $feeds,
  );
  return drupal_render($aggregator_page_opml);
}

/**
 * Prints the OPML page for the feed.
 *
 * @param array $variables
 *   An associative array containing:
 *   - feeds: An array of the feeds to theme.
 *
 * @ingroup themeable
 */
function theme_aggregator_page_opml($variables) {
  $feeds = $variables['feeds'];

  drupal_add_http_header('Content-Type', 'text/xml; charset=utf-8');

  $output  = "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
  $output .= "<opml version=\"1.1\">\n";
  $output .= "<head>\n";
  $output .= '<title>' . check_plain(Drupal::config('system.site')->get('name')) . "</title>\n";
  $output .= '<dateModified>' . gmdate(DATE_RFC2822, REQUEST_TIME) . "</dateModified>\n";
  $output .= "</head>\n";
  $output .= "<body>\n";
  foreach ($feeds as $feed) {
    $output .= '<outline text="' . check_plain($feed->title) . '" xmlUrl="' . check_url($feed->url) . "\" />\n";
  }
  $output .= "</body>\n";
  $output .= "</opml>\n";

  print $output;
}

/**
 * Prepares variables for aggregator summary templates.
 *
 * Default template: aggregator-summary-items.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - source: A Drupal\aggregator\Entity\Feed instance representing
 *     the feed source.
 *   - summary_items: An array of feed items.
 */
function template_preprocess_aggregator_summary_items(&$variables) {
  $variables['title'] = check_plain($variables['source'] instanceof EntityInterface ? $variables['source']->label() : $variables['source']->title);
  $summary_items = array();
  foreach (element_children($variables['summary_items']) as $key) {
    $summary_items[] = $variables['summary_items'][$key];
  }
  $variables['summary_list'] = array(
    '#theme' => 'item_list',
    '#items' => $summary_items,
  );
  $variables['source_url'] = $variables['source'] instanceof EntityInterface ? $variables['source']->url->value : $variables['source']->url;
}

/**
 * Processes variables for aggregator summary item templates.
 *
 * Default template: aggregator-summary-item.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - aggregator_item: The feed item.
 *   - view_mode: How the item is being displayed.
 */
function template_preprocess_aggregator_summary_item(&$variables) {
  $item = $variables['aggregator_item'];

  $variables['item_url'] = l(check_plain($item->label()), check_url(url($item->link->value, array('absolute' => TRUE))), array(
    'attributes' => array(
      'class' => array('feed-item-url',),
    ),
  ));
  $datetime = array(
    '#theme' => 'datetime',
    '#attributes' => array(
      'datetime' => format_date($item->timestamp->value, 'html_datetime', '', 'UTC'),
      'class' => array('feed-item-age',),
    ),
    '#text' => t('%age old', array('%age' => format_interval(REQUEST_TIME - $item->timestamp->value))),
    '#html' => TRUE,
  );
  $variables['item_age'] = drupal_render($datetime);
}

/**
 * Prepares variables for aggregator feed source templates.
 *
 * Default template: aggregator-feed-source.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - aggregator_feed: A Drupal\aggregator\Entity\Feed instance
 *     representing the feed source.
 */
function template_preprocess_aggregator_feed_source(&$variables) {
  $feed = $variables['aggregator_feed'];

  $feed_icon = array(
    '#theme' => 'feed_icon',
    '#url' => $feed->url->value,
    '#title' => t('!title feed', array('!title' => $feed->label())),
  );
  $variables['source_icon'] = drupal_render($feed_icon);

  if (!empty($feed->image->value) && $feed->label() && !empty($feed->link->value)) {
    $image = array(
      '#theme' => 'image',
      '#path' => $feed->image->value,
      '#alt' => $feed->title->value,
    );
    $variables['source_image'] = l($image, $feed->link->value, array('html' => TRUE, 'attributes' => array('class' => 'feed-image')));
  }
  else {
    $variables['source_image'] = '';
  }
  $variables['source_description'] = aggregator_filter_xss($feed->description->value);
  $variables['source_url'] = check_url(url($feed->link->value, array('absolute' => TRUE)));

  if ($feed->checked) {
    $variables['last_checked'] = t('@time ago', array('@time' => format_interval(REQUEST_TIME - $feed->checked->value)));
  }
  else {
    $variables['last_checked'] = t('never');
  }

  if (user_access('administer news feeds')) {
    $variables['last_checked'] = l($variables['last_checked'], 'admin/config/services/aggregator');
  }

  $variables['attributes']['class'][] = 'feed-source';
}
