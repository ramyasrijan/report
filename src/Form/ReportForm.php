<?php

namespace Drupal\layout_builder_usage_reports\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;

/**
 * Class ReportForm.
 */
class ReportForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'report_form';
  }

  /*
   * take a string and return true if it starts with the specified character/string
   */
  private function startsWith( $haystack, $needle ) {
     $length = strlen( $needle );
     return substr( $haystack, 0, $length ) === $needle;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $lbur_bundle = \Drupal::state()->get('lbur_bundle');
    $lbur_provider = \Drupal::state()->get('lbur_provider');
    $lbur_language = \Drupal::state()->get('lbur_language');
    $lbur_block_type = \Drupal::state()->get('lbur_block_type');
    $lbur_paragraph_type = \Drupal::state()->get('lbur_paragraph_type');

    $header = array(
      'entity_id' => t('Node ID'),
      'entity_title' => t('Node Title'),
      'bundle' => t('Bundle'),
      'langcode' => t('Language'),
      'component_id' => t('Plugin ID'),
      'label' => t('Label'),
      'provider' => t('Provider'),
    );
    $database = \Drupal::database();
    $result = array();
    $options = array();
    $display_reset = TRUE;
    if($database->schema()->tableExists('node__layout_builder__layout')){
      $query = $database->select('node__layout_builder__layout', 'nlb');
      $query->join('node_field_data', 'nfd', 'nlb.entity_id = nfd.nid AND nlb.langcode = nfd.langcode');
      $query->fields('nlb', ['bundle', 'entity_id', 'langcode', 'layout_builder__layout_section']);
      $query->fields('nfd', ['title']);
      // If no filters and displaying all results, limit query.
      if(empty($lbur_bundle) && empty($lbur_provider) && empty($lbur_language) && empty($lbur_block_type) && empty($lbur_paragraph_type)){
        $query->range(0, 500);
        $display_reset = FALSE;
      }
      $result = $query->execute();
    }
    $bundles_set = array();
    $providers_set = array();
    $languages_set = array();
    $block_types_set = array();
    $paragraph_types_set = array();
    foreach ($result as $record) {
      $node_title = $record->title;
      if (strlen($node_title) > 25)
        $node_title = substr($node_title, 0, 23) . '..';
      $node_link = Link::createFromRoute($node_title, 'entity.node.canonical', ['node' => $record->entity_id]);
      $bundles_set[$record->bundle] = $record->bundle;
      $languages_set[$record->langcode] = $record->langcode;
      
      if(!empty($lbur_bundle) && $lbur_bundle != $record->bundle){
        continue;
      }
      if(!empty($lbur_language) && $lbur_language != $record->langcode){
        continue;
      }
      $serialized_data = $record->layout_builder__layout_section;
      $unserialized_data = unserialize($serialized_data);
      //ksm($unserialized_data);
      $components = $unserialized_data->getComponents();
      foreach ($components as $components_id => $component) {
        $pluginId =  $component->getPluginId();
        /*
         * inline_block:title_and_supporting_message
         * component:testimonial:node
         * if pluginId starts with inline_block, get second part of the ID after :, It's block type
         * if pluginId starts with component, get second part of the ID after :, It's paragraph type
         */
        $pluginIdPartsArray = explode(":",$pluginId);
        $block_type = '';
        $paragraph_type = '';
        if($this->startsWith($pluginId, "inline_block:")){
          // It's block type
          $block_type = (isset($pluginIdPartsArray[1]) ? $pluginIdPartsArray[1] : '');
          if(!empty($block_type)){
            $block_types_set[$block_type] = $block_type;
          }
        }elseif($this->startsWith($pluginId, "component:")){
          // It's paragraph type
          $paragraph_type = (isset($pluginIdPartsArray[1]) ? $pluginIdPartsArray[1] : '');
          if(!empty($paragraph_type)){
            $paragraph_types_set[$paragraph_type] = $paragraph_type;
          }
        }
        $configuration =  $component->get("configuration");
        //ksm($configuration);
        $label = (isset($configuration["label"]) && !empty($configuration["label"]) ? $configuration["label"] : '');
        $provider = (isset($configuration["provider"]) && !empty($configuration["provider"]) ? $configuration["provider"] : 'No Provider');
        $providers_set[$provider] = $provider;
        if(!empty($lbur_provider) && $lbur_provider != $provider){
          continue;
        }
        if(!empty($lbur_block_type) && $lbur_block_type != $block_type){
          continue;
        }
        if(!empty($lbur_paragraph_type) && $lbur_paragraph_type != $paragraph_type){
          continue;
        }
        $label_machine_name = preg_replace("/[^A-Za-z0-9]/", '', $label);
        $table_row_id = $record->entity_id."-".$record->langcode."-".$pluginId."-".$label_machine_name;
        if (strlen($label) > 25)
          $label = substr($label, 0, 23) . '..';
        $options[$table_row_id] = array(
          'entity_id' => $record->entity_id,
          'entity_title' => $node_link,
          'bundle' => $record->bundle,
          'langcode' => $record->langcode,
          'component_id' => $pluginId,
          'label' => $label,
          'provider' => $provider,
        );
      }
    }
    $form['filtergroup'] = array(
      '#type' => 'fieldset', 
      '#title' => t('Filter'), 
      '#attributes' => array('class' => array('container-inline')), 
    );
    if(!empty($options)){
      $form['filtergroup']['bundle'] = [
        '#empty_value' => '',
        '#type' => 'select',
        '#title' => $this->t('Node Bundle'),
        '#options' => $bundles_set,
        '#default_value' => $lbur_bundle,
        '#weight' => '0',
      ];
      $form['filtergroup']['provider'] = [
        '#empty_value' => '',
        '#type' => 'select',
        '#title' => $this->t('Provider'),
        '#options' => $providers_set,
        '#default_value' => $lbur_provider,
        '#weight' => '0',
      ];
      $form['filtergroup']['language'] = [
        '#empty_value' => '',
        '#type' => 'select',
        '#title' => $this->t('Node language'),
        '#options' => $languages_set,
        '#default_value' => $lbur_language,
        '#weight' => '0',
      ];
      $form['filtergroup']['block_type'] = [
        '#empty_value' => '',
        '#type' => 'select',
        '#title' => $this->t('Block Type'),
        '#options' => $block_types_set,
        '#default_value' => $lbur_block_type,
        '#weight' => '0',
      ];
      $form['filtergroup']['paragraph_type'] = [
        '#empty_value' => '',
        '#type' => 'select',
        '#title' => $this->t('Paragraph Type'),
        '#options' => $paragraph_types_set,
        '#default_value' => $lbur_paragraph_type,
        '#weight' => '0',
      ];
      $form['filtergroup']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Filter'),
        '#weight' => '8',
      ];
    }
    if ($display_reset) {
      $form['filtergroup']['reset'] = [
        '#type' => 'submit',
        '#value' => t('Reset the filter'),
        '#submit' => [[$this, 'resetForm']],
      ];
    }
    if (!empty($options)) {
      $form['tablehelptext'] = [
        '#markup' => $this->t('@count results shown below!', array('@count' => count($options))),
        '#weight' => '9',
      ];
    }
    $form['report'] = [
      '#type' => 'table',
      '#title' => $this->t('Report'),
      '#description' => $this->t('@count results shown below!', array('@count' => count($options))),
      '#header' => $header,
      '#rows' => $options,
      '#empty' => t('No layout data found'),
      '#weight' => '9',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValues() as $key => $value) {
      // @TODO: Validate fields.
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    \Drupal::state()->set('lbur_bundle',$form_state->getValues()["bundle"]);
    \Drupal::state()->set('lbur_provider',$form_state->getValues()["provider"]);
    \Drupal::state()->set('lbur_language',$form_state->getValues()["language"]);
    \Drupal::state()->set('lbur_block_type',$form_state->getValues()["block_type"]);
    \Drupal::state()->set('lbur_paragraph_type',$form_state->getValues()["paragraph_type"]);
  }

  /**
   * Reset the filter.
   */
  public function resetForm() {
    \Drupal::state()->delete('lbur_bundle');
    \Drupal::state()->delete('lbur_provider');
    \Drupal::state()->delete('lbur_language');
    \Drupal::state()->delete('lbur_block_type');
    \Drupal::state()->delete('lbur_paragraph_type');
  }
 }
