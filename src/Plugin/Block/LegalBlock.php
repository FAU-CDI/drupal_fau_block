<?php

namespace Drupal\fau_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides a 'Distillery Legal' Block.
 *
 * @Block(
 *   id = "fau_block",
 *   admin_label = @Translation("FAU Legal Block"),
 *   category = @Translation("FAU Legal Block"),
 * )
 */
class LegalBlock extends BlockBase {

    const DEFAULT = "default";
    const CUSTOM = "custom";

    /**
     * {@inheritDoc}
     */
    public function defaultConfiguration() {
        return [
            'logo_template' => "Empty",
            'link_template' => "Emtpy",
            'label_display' => 0,
            'html' => "",
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function blockForm($form, FormStateInterface $form_state) {
        // Take associative arrays of the keys otherwise submit will save
        // the the index of the selected element instaed of the string.
        $templates = $this->getTemplates();
        $getOptions = function ($templates) {
            return array_combine(array_keys($templates), array_keys($templates));
        };
        $linkTemplates = $getOptions($templates['links']);
        $logoTemplates = $getOptions($templates['logos']);

        $form['link_template'] = [
            '#type' => 'select',
            '#title' => $this->t('Link Template'),
            '#description' => $this->t('The link template for the block'),
            '#default_value' => $this->configuration['link_template'],
            '#options' => $linkTemplates,
        ];

        $form['logo_template'] = [
            '#type' => 'select',
            '#title' => $this->t('Logo Template'),
            '#description' => $this->t('The logo template for the block'),
            '#default_value' => $this->configuration['logo_template'],
            '#options' => $logoTemplates,
        ];

        // TODO: introduce a selection for the twig templates if desired.

        return $form;
    }

    /**
     * {@inheritDoc}
     */
    public function blockSubmit($form, FormStateInterface $form_state) {
        $values = $form_state->getValues();
        $this->configuration['link_template'] = $values['link_template'];
        $this->configuration['logo_template'] = $values['logo_template'];
        // Build once on submit and reuse that HTML.
        // Generating the HTML from the template on each build()
        // runs the risk of the template not being loaded due to
        // a cache clear. If the template is not loaded a `drush cr` fixes it.
        // Sadly there seems to be no way to do this from code...
        $this->configuration['html'] = $this->doBuild();
    }

    /**
     * {@inheritDoc}
     */
    function build() {
        return ['#children' => $this->configuration['html']];
    }

    /**
     * {@inheritDoc}
     */
    public function doBuild() {
        $templates = $this->getTemplates();

        $links = $templates['links'][$this->configuration['link_template']] ?? [];

        /** @var \Drupal\Core\File\FileUrlGenerator */
        $generator = \Drupal::service("file_url_generator"); // TODO: dependency injection

        $logoTemplate = $this->configuration["logo_template"];
        $logos = $templates['logos'][$logoTemplate];
        foreach ($logos as $logo) {
            // Get the correct URL to the image.
            $logo['image'] = $generator->generate($logo['image'])->toString();
        }

        // Compute the minimal height for a logo.
        $minLogoHeight = 0;
        if ($logos ? count($logos) : 0 > 0) {
            $minLogoHeight = current($logos)['height'];
            foreach ($logos as $logo) {
                $height = $logo['height'];
                if ($height < $minLogoHeight) {
                    $minLogoHeight = $height;
                }
            }
        }

        $renderable = [
            '#theme' => 'block--fau_block--default',
            '#height' => $minLogoHeight,
            '#logos' => $logos,
            '#links' => $links,
        ];

        return \Drupal::service('renderer')->render($renderable);
    }

    /**
     * Get all templates for blocks.
     *
     * Includes default templates from this module as well as the custom ones.
     *
     * @return array
     *   The templates. @see /config/install/fau_block.templates.yml for the general format.
     */
    protected function getTemplates(): array {
        $defaultTemplates = \Drupal::configFactory()->getEditable('fau_block.templates')->getRawData();
        unset($defaultTemplates['_core']);
        $defaultTemplates = $this->sanitizeValues($defaultTemplates);

        $defaultLogos = $defaultTemplates['logos'] ?? [];
        // Prefix the path to the module to default images.
        $defaultLogos = self::prefixImages($defaultLogos, self::DEFAULT);

        // Get the logos from the custom config.
        $customTemplatesLocation = 'public://fau_block/templates.yaml';
        $customTemplatesString = file_exists($customTemplatesLocation) ? file_get_contents($customTemplatesLocation) : FALSE;

        $customTemplates = [];
        // Skip if there's no custom config.
        if ($customTemplatesString) {
            $customTemplates = Yaml::parse($customTemplatesString);
            $customTemplates = $this->sanitizevalues($customTemplates);
        }
        // Prefix the path to custom dir to the custom images.
        $customLogos = $customTemplates['logos'] ?? [];
        $customLogos = self::prefixImages($customLogos, self::CUSTOM);

        // Get link templates.
        $defaultLinks = $defaultTemplates['links'] ?? [];
        $customLinks = $customTemplates['links'] ?? [];

        // Merge default with custom.
        $logoTemplates = array_merge($defaultLogos, $customLogos);
        $linkTemplates = array_merge($defaultLinks, $customLinks);

        return [
            'links' => $linkTemplates,
            'logos' => $logoTemplates,
        ];
    }

   /**
     * Prefixes the path to the image according to their location.
     *
     * @param array $logoTemplates
     *   The 'logos' section of the templates config.
     * @param string $context
     *   Indicating which config the logo templates belong to.
     *   Either self::DEFAULT or self::CUSTOM.
     *
     * @return array
     *   The 'logos' section with the correctly prefixed.
     */
    protected static function prefixImages(array $logoTemplates, string $context = self::DEFAULT): array {
        // Prefix the module path to the default template images.
        $defaultPath = \Drupal::service('extension.list.module')->getPath('fau_block'); // TODO: dependency injection
        foreach ($logoTemplates as $name => $logos) {
            foreach ($logos as $index => $logo) {
                switch ($context) {
                    case self::DEFAULT:
                        $logoTemplates[$name][$index]['image'] = "/$defaultPath/images/$name/" . $logo['image'];
                        break;
                    case self::CUSTOM:
                        $logoTemplates[$name][$index]['image'] = "public://fau_block/$name/" . $logo['image'];
                        break;
                }
            }
        }
        return $logoTemplates;
    }

    /**
     * Loop though all valus in the template and sanitize them.
     *
     * @param array $templateTypes
     *   The template values from configs.
     *
     * @return array
     *   The sanizized template.
     */
    protected function sanitizeValues(array $templateTypes): array {
        $safeAttr = function(string $value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); };
        $safeHtml = function(string $text) { return strip_tags($text); };
        $id = function($value) { return $value; };
        // Mapping the different values to each sanitizing function.
        $sanitizing = [
            'logos' => [
                'image' => $safeAttr,
                'alt' => $safeAttr,
                'height' => $id,
                'url' => $safeAttr,
            ],
            'links' => [
                'text' => $safeHtml,
                'url' => $safeHtml,
            ],
        ];

        foreach ($templateTypes as $templateType => $templates) {
            foreach ($templates as $templateName => $template) {
                foreach ($template as $idx => $entry) {
                    foreach ($entry as $key => $value) {
                        $templateTypes[$templateType][$templateName][$idx][$key] = $sanitizing[$templateType][$key]($value);
                    }
                }
            }
        }
        return $templateTypes;
    }
}
