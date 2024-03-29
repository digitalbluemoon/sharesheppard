<?php

/**
 * @file
 * Contains \Drupal\forum\ForumBreadcrumbBuilder.
 */

namespace Drupal\forum;

use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityManager;
use Drupal\forum\ForumManagerInterface;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;

/**
 * Class to define the forum breadcrumb builder.
 */
class ForumBreadcrumbBuilder implements BreadcrumbBuilderInterface {

  /**
   * Configuration object for this builder.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Stores the Entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManager
   */
  protected $entityManager;

  /**
   * The forum manager service.
   *
   * @var \Drupal\forum\ForumManagerInterface
   */
  protected $forumManager;

  /**
   * Constructs a new ForumBreadcrumbBuilder.
   *
   * @param \Drupal\Core\Entity\EntityManager
   *   The entity manager.
   * @param \Drupal\Core\Config\ConfigFactory $configFactory
   *   The configuration factory.
   * @param \Drupal\forum\ForumManagerInterface $forum_manager
   *   The forum manager service.
   */
  public function __construct(EntityManager $entity_manager, ConfigFactory $configFactory, ForumManagerInterface $forum_manager) {
    $this->entityManager = $entity_manager;
    $this->config = $configFactory->get('forum.settings');
    $this->forumManager = $forum_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $attributes) {
    // @todo This only works for legacy routes. Once node/% and forum/% are
    //   converted to the new router this code will need to be updated.
    if (isset($attributes['_drupal_menu_item']) && ($item = $attributes['_drupal_menu_item']) && $item['path'] == 'node/%') {
      $node = $item['map'][1];
      // Load the object in case of missing wildcard loaders.
      $node = is_object($node) ? $node : node_load($node);
      if ($this->forumManager->checkNodeType($node)) {
        $breadcrumb = $this->forumPostBreadcrumb($node);
      }
    }

    if (!empty($attributes[RouteObjectInterface::ROUTE_NAME]) && $attributes[RouteObjectInterface::ROUTE_NAME] == 'forum.page' && isset($attributes['taxonomy_term'])) {
      $breadcrumb = $this->forumTermBreadcrumb($attributes['taxonomy_term']);
    }

    if (!empty($breadcrumb)) {
      return $breadcrumb;
    }
  }

  /**
   * Builds the breadcrumb for a forum post page.
   */
  protected function forumPostBreadcrumb($node) {
    $vocabulary = $this->entityManager->getStorageController('taxonomy_vocabulary')->load($this->config->get('vocabulary'));

    $breadcrumb[] = l(t('Home'), NULL);
    $breadcrumb[] = l($vocabulary->label(), 'forum');
    if ($parents = taxonomy_term_load_parents_all($node->forum_tid)) {
      $parents = array_reverse($parents);
      foreach ($parents as $parent) {
        $breadcrumb[] = l($parent->label(), 'forum/' . $parent->id());
      }
    }
    return $breadcrumb;
  }

  /**
   * Builds the breadcrumb for a forum term page.
   */
  protected function forumTermBreadcrumb($term) {
    $vocabulary = $this->entityManager->getStorageController('taxonomy_vocabulary')->load($this->config->get('vocabulary'));

    $breadcrumb[] = l(t('Home'), NULL);
    if ($term->tid) {
      // Parent of all forums is the vocabulary name.
      $breadcrumb[] = l($vocabulary->label(), 'forum');
    }
    // Add all parent forums to breadcrumbs.
    if ($term->parents) {
      foreach (array_reverse($term->parents) as $parent) {
        if ($parent->id() != $term->id()) {
          $breadcrumb[] = l($parent->label(), 'forum/' . $parent->id());
        }
      }
    }
    return $breadcrumb;
  }

}
