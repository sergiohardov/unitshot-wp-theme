<?php declare(strict_types=1);

namespace UnitshotTheme\Controllers\Pages;

use Timber\Timber;
use UnitshotTheme\Classes\Fields;
use UnitshotTheme\Controllers\Blocks\BrandLogosController;
use UnitshotTheme\Controllers\Blocks\CategoryLinksController;
use UnitshotTheme\Controllers\Blocks\CategoryShowcaseController;
use UnitshotTheme\Controllers\Blocks\ClientLogosController;
use UnitshotTheme\Controllers\Blocks\FeedbackInfoController;
use UnitshotTheme\Controllers\Blocks\PopularProductsController;
use UnitshotTheme\Controllers\Blocks\SliderPostsController;
use UnitshotTheme\Controllers\Blocks\SliderReviewsController;
use UnitshotTheme\Controllers\Blocks\TextImageController;

class HomeController
{
    private array $fields;

    function __construct()
    {
        $this->fields = Fields::get_front_page();
    }

    public function get_blocks_data(): string {
        $blocks     = $this->fields['blocks'];
        $html       = '';

        foreach ($blocks as $block) {
            $layout = $block['acf_fc_layout'] ?? null;

            $block = match ($layout) {
                'block_brand_logos'         => is_array($block['unitshot_blocks_brand_logos'] ?? null)      ? new BrandLogosController($block['unitshot_blocks_brand_logos'])               : null,
                'block_popular_products'    => is_array($block['unitshot_blocks_popular_products'] ?? null) ? new PopularProductsController($block['unitshot_blocks_popular_products'])     : null,
                'block_category_showcase'   => is_array($block['unitshot_blocks_category_showcase']?? null) ? new CategoryShowcaseController($block['unitshot_blocks_category_showcase'])   : null,
                'block_category_links'      => is_array($block['unitshot_blocks_category_links'] ?? null)   ? new CategoryLinksController($block['unitshot_blocks_category_links'])         : null,
                'block_text_image'          => is_array($block['unitshot_blocks_text_image'] ?? null)       ? new TextImageController($block['unitshot_blocks_text_image'])                 : null,
                'block_client_logos'        => is_array($block['unitshot_blocks_client_logos'] ?? null)     ? new ClientLogosController($block['unitshot_blocks_client_logos'])             : null,
                'block_slider_reviews'      => is_array($block['unitshot_blocks_slider_reviews'] ?? null)   ? new SliderReviewsController($block['unitshot_blocks_slider_reviews'])         : null,
                'block_feedback_info'       => is_array($block['unitshot_blocks_feedback_info'] ?? null)    ? new FeedbackInfoController($block['unitshot_blocks_feedback_info'])           : null,
                'block_slider_posts'        => is_array($block['unitshot_blocks_slider_posts'] ?? null)     ? new SliderPostsController($block['unitshot_blocks_slider_posts'])             : null,
                default => null
            };

            if($block !== null) $html .= $block->compile();
        }

        return $html;
    }

    public function render(): void
    {
        Timber::render("@pages/home.twig", [
            'blocks' => $this->get_blocks_data(),
        ]);
    }
}
