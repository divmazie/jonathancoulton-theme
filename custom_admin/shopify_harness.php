<?php

namespace jct;

use FetchApp\API\Currency;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Uri;
use jct\Shopify\SynchronousAPIClient;
use jct\Shopify\Product;
use FetchApp\API\Product as FetchProduct;

/**
 * Sync plan
 * Use products for monolithic updates. Don't update stuff outside of the
 * product flow, which is the fastest.
 *
 * Either products
 *  - $onLocal && !$onShopify: POST
 *  - $onLocal && $onShopify: PUT
 *      We PUT for EVERYTHING because the single query is cheaper than
 *      actually fetching the stuff we'd need to fetch
 *
 *      (OR we use a local approximation of syncedness
 *      and have a force option, splitting this into two... )
 *
 *  - !$onLocal && $onShopify: DELETE
 *
 * In order to do this we need to be able to tell shopify WTF
 * we are talking about, i.e. we need to correlate the stuff from our
 * database and shopify's. Preferably without storing a bunch of nasty
 * keys everywhere. For any objects we want to actually UPDATE vs overwrite
 * we need a way to get their ID and put it into the object coming out of
 * OUR system.
 *
 * Products (backtrack from variants)
 *  - variants (the SKU is the Post ID and configName... we can key an array with this)
 *  - images (OVERWRITE EM WHO CARES--we upload a key, not the base64)
 *  - options (SAME... NO IMPACT)
 *  - metafields (I hope they are the same and that the key is enough to overwrite them)
 *
 * Do fetch later... use the trick from github to actually upload the s3 urls... that one
 * should be relatively quick (I hope)
 */
echo "<pre>";

//$apiClient = Util::get_shopify_api_client();
$fetch = Util::get_fetch_api_client();

$karaokeSKUs = [
    'karaoke_a_laptop_like_you', 'karaoke_a_talk_with_george', 'karaoke_alone_at_home', 'karaoke_artificial_heart',
    'karaoke_baby_got_back', 'karaoke_better', 'karaoke_betty_and_me', 'karaoke_big_bad_world_one',
    'karaoke_bills_bills_bills', 'karaoke_bozos_lament', 'karaoke_brand_new_sucker', 'karaoke_brookline',
    'karaoke_chiron_beta_prime', 'karaoke_christmas_is_interesting', 'karaoke_code_monkey', 'karaoke_creepy_doll',
    'karaoke_curl', 'karaoke_dance_sj_rave', 'karaoke_dance_soterios_johnson_dance', 'karaoke_de-evolving',
    'karaoke_dissolve', 'karaoke_down_today', 'karaoke_drinking_with_you', 'karaoke_first_of_may', 'karaoke_fraud',
    'karaoke_gamblers_prayer', 'karaoke_glasses', 'karaoke_good_morning_tucson', 'karaoke_i_crush_everything',
    'karaoke_i_feel_fantastic', 'karaoke_i_hate_california', 'karaoke_ikea', 'karaoke_im_a_mason_now',
    'karaoke_im_having_a_party', 'karaoke_im_your_moon', 'karaoke_je_suis_rick_springfield',
    'karaoke_just_as_long_as_me', 'karaoke_make_you_cry', 'karaoke_mandelbrot_set', 'karaoke_millionaire_girlfriend',
    'karaoke_mr_fancy_pants', 'karaoke_my_monkey', 'karaoke_nemeses', 'karaoke_nobody_loves_you_like_me',
    'karaoke_not_about_you', 'karaoke_now_i_am_an_arsonist', 'karaoke_over_there', 'karaoke_pull_the_string',
    'karaoke_re_your_brains', 'karaoke_screwed', 'karaoke_seahorse', 'karaoke_shop_vac',
    'karaoke_skullcrusher_mountain', 'karaoke_skymall', 'karaoke_soft_rocked_by_me', 'karaoke_someone_is_crazy',
    'karaoke_sticking_it_to_myself', 'karaoke_still_alive', 'karaoke_sucker_punch', 'karaoke_summers_over',
    'karaoke_take_care_of_me', 'karaoke_that_spells_dna', 'karaoke_the_big_boom', 'karaoke_the_future_soon',
    'karaoke_the_stache', 'karaoke_the_town_crotch', 'karaoke_the_world_belongs_to_you', 'karaoke_till_the_money_comes',
    'karaoke_today_with_your_wife', 'karaoke_todd_the_t1000', 'karaoke_tom_cruise_crazy', 'karaoke_under_the_pines',
    'karaoke_want_you_gone', 'karaoke_when_you_go', 'karaoke_womb_with_a_view', 'karaoke_you_could_be_her',
    'karaoke_you_ruined_everything',
];
$s3FileNames = [
    'karaoke_a_laptop_like_you.mp4', 'karaoke_a_laptop_like_you.zip', 'karaoke_a_talk_with_george.mp4',
    'karaoke_a_talk_with_george.zip', 'karaoke_alone_at_home.zip', 'karaoke_artificial_heart.zip',
    'karaoke_baby_got_back.mp4', 'karaoke_baby_got_back.zip', 'karaoke_better.mp4', 'karaoke_better.zip',
    'karaoke_betty_and_me.mp4', 'karaoke_betty_and_me.zip', 'karaoke_big_bad_world_one.mp4',
    'karaoke_big_bad_world_one.zip', 'karaoke_bills_bills_bills.mp4', 'karaoke_bozos_lament.mp4',
    'karaoke_bozos_lament.zip', 'karaoke_brand_new_sucker.mp4', 'karaoke_brand_new_sucker.zip', 'karaoke_brookline.mp4',
    'karaoke_brookline.zip', 'karaoke_chiron_beta_prime.mp4', 'karaoke_chiron_beta_prime.zip',
    'karaoke_christmas_is_interesting.mp4', 'karaoke_christmas_is_interesting.zip', 'karaoke_code_monkey.mp4',
    'karaoke_code_monkey.zip', 'karaoke_creepy_doll.mp4', 'karaoke_creepy_doll.zip', 'karaoke_curl.mp4',
    'karaoke_curl.zip', 'karaoke_dance_sj_rave.mp4', 'karaoke_dance_sj_rave.zip',
    'karaoke_dance_soterios_johnson_dance.mp4', 'karaoke_dance_soterios_johnson_dance.zip', 'karaoke_de-evolving.mp4',
    'karaoke_de-evolving.zip', 'karaoke_dissolve.zip', 'karaoke_down_today.zip', 'karaoke_drinking_with_you.mp4',
    'karaoke_drinking_with_you.zip', 'karaoke_first_of_may.mp4', 'karaoke_first_of_may.zip', 'karaoke_fraud.zip',
    'karaoke_gamblers_prayer.mp4', 'karaoke_gamblers_prayer.zip', 'karaoke_glasses.zip',
    'karaoke_good_morning_tucson.zip', 'karaoke_i_crush_everything.mp4', 'karaoke_i_crush_everything.zip',
    'karaoke_i_feel_fantastic.mp4', 'karaoke_i_feel_fantastic.zip', 'karaoke_i_hate_california.mp4',
    'karaoke_i_hate_california.zip', 'karaoke_ikea.mp4', 'karaoke_ikea.zip', 'karaoke_im_a_mason_now.mp4',
    'karaoke_im_a_mason_now.zip', 'karaoke_im_having_a_party.mp4', 'karaoke_im_having_a_party.zip',
    'karaoke_im_your_moon.mp4', 'karaoke_im_your_moon.zip', 'karaoke_je_suis_rick_springfield.zip',
    'karaoke_just_as_long_as_me.mp4', 'karaoke_just_as_long_as_me.zip', 'karaoke_make_you_cry.mp4',
    'karaoke_make_you_cry.zip', 'karaoke_mandelbrot_set.mp4', 'karaoke_mandelbrot_set.zip',
    'karaoke_millionaire_girlfriend.mp4', 'karaoke_millionaire_girlfriend.zip', 'karaoke_mr_fancy_pants.mp4',
    'karaoke_mr_fancy_pants.zip', 'karaoke_my_monkey.mp4', 'karaoke_my_monkey.zip', 'karaoke_nemeses.zip',
    'karaoke_nobody_loves_you_like_me.zip', 'karaoke_not_about_you.mp4', 'karaoke_not_about_you.zip',
    'karaoke_now_i_am_an_arsonist.zip', 'karaoke_over_there.mp4', 'karaoke_over_there.zip',
    'karaoke_pull_the_string.mp4', 'karaoke_pull_the_string.zip', 'karaoke_re_your_brains.mp4',
    'karaoke_re_your_brains.zip', 'karaoke_screwed.mp4', 'karaoke_screwed.zip', 'karaoke_seahorse.mp4',
    'karaoke_seahorse.zip', 'karaoke_shop_vac.mp4', 'karaoke_shop_vac.zip', 'karaoke_skullcrusher_mountain.mp4',
    'karaoke_skullcrusher_mountain.zip', 'karaoke_skymall.mp4', 'karaoke_skymall.zip', 'karaoke_soft_rocked_by_me.mp4',
    'karaoke_soft_rocked_by_me.zip', 'karaoke_someone_is_crazy.mp4', 'karaoke_someone_is_crazy.zip',
    'karaoke_sticking_it_to_myself.zip', 'karaoke_still_alive.zip', 'karaoke_sucker_punch.zip',
    'karaoke_summers_over.mp4', 'karaoke_summers_over.zip', 'karaoke_take_care_of_me.mp4',
    'karaoke_take_care_of_me.zip', 'karaoke_that_spells_dna.mp4', 'karaoke_that_spells_dna.zip',
    'karaoke_the_big_boom.mp4', 'karaoke_the_big_boom.zip', 'karaoke_the_future_soon.mp4',
    'karaoke_the_future_soon.zip', 'karaoke_the_stache.zip', 'karaoke_the_town_crotch.mp4',
    'karaoke_the_town_crotch.zip', 'karaoke_the_world_belongs_to_you.zip', 'karaoke_till_the_money_comes.mp4',
    'karaoke_till_the_money_comes.zip', 'karaoke_today_with_your_wife.zip', 'karaoke_todd_the_t1000.mp4',
    'karaoke_todd_the_t1000.zip', 'karaoke_tom_cruise_crazy.mp4', 'karaoke_tom_cruise_crazy.zip',
    'karaoke_under_the_pines.mp4', 'karaoke_under_the_pines.zip', 'karaoke_want_you_gone.zip',
    'karaoke_when_you_go.mp4', 'karaoke_when_you_go.zip', 'karaoke_womb_with_a_view.mp4',
    'karaoke_womb_with_a_view.zip', 'karaoke_you_could_be_her.mp4', 'karaoke_you_could_be_her.zip',
    'karaoke_you_ruined_everything.mp4', 'karaoke_you_ruined_everything.zip', 'karaoke_alone_at_home.mp4',
    'karaoke_artificial_heart.mp4', 'karaoke_dissolve.mp4', 'karaoke_down_today.mp4', 'karaoke_fraud.mp4',
    'karaoke_glasses.mp4', 'karaoke_good_morning_tucson.mp4', 'karaoke_je_suis_rick_springfield.mp4',
    'karaoke_nemeses.mp4', 'karaoke_nobody_loves_you_like_me.mp4', 'karaoke_now_i_am_an_arsonist.mp4',
    'karaoke_sticking_it_to_myself.mp4', 'karaoke_still_alive.mp4', 'karaoke_sucker_punch.mp4',
    'karaoke_the_stache.mp4', 'karaoke_the_world_belongs_to_you.mp4', 'karaoke_today_with_your_wife.mp4',
    'karaoke_want_you_gone.mp4',
];
$extensions = ['.mp4', '.zip'];
$s3DirUrl = "https://s3.amazonaws.com/joco-songs-new/karaoke/";

foreach($karaokeSKUs as $sku) {
    $prodUrls = [];

    foreach($extensions as $ext) {
        $extFileName = "$sku$ext";
        if(in_array($extFileName, $s3FileNames)) {
            $prodUrls[] = ["url" => "$s3DirUrl$extFileName", "name" => $extFileName];
        }
    }


    if($prodUrls) {
        $fetchProduct = new FetchProduct();
        $fetchProduct->setProductID($sku);
        $fetchProduct->setSKU($sku);
        $fetchProduct->setName($sku);
        $fetchProduct->setPrice(1);
        $fetchProduct->setCurrency(Currency::USD);
        //var_dump($fetchProduct);
        var_dump($prodUrls);
        var_dump($fetchProduct->update([], $prodUrls));
        //die();
    }
}
die();
//var_dump($otherClient->makeCall('admin/custom_collections'));
//$response = $apiClient->shopifyPagedGet('admin/products.json');

//$this->shopifyPagedGet('admin/products.json')
//var_dump($response);
//var_dump($apiClient->getAllProducts());

//var_dump($apiClient->shopifyPagedGet('/admin/products/9133128710/metafields.json', ['metafield[owner_resource]' => 'product']));


//var_dump($apiClient->shopifyPagedGet('/admin/custom_collections.json'));


$remotepord = $apiClient->getAllProducts(['product_type' => 'Karaoke']);
var_dump($remotepord);
//var_dump($remotepord);
die();
//var_dump($remotepord);
//die();
//$prod0 = $products[0];11
$lcoals = SyncManager::getMusicStoreProducts();

//var_dump(Product::fromProductProvider($lcoals[1])->putArray());
//die();
SyncManager::sync($apiClient, $lcoals, $remotepord);
die();
//var_dump($products);
echo "beep";
//var_dump($pro1->postArray());

$toPost->shopifyAPIResponse($apiClient->postProduct(Product::fromProductProvider($toPost)));

die();

$prod0 = Product::instancesFromArray($response->getResponseArray()['products']);
var_dump($prod0);
//echo implode("\n", array_keys($prod0->variants[0]));

echo implode("\n", array_keys($response->getResponseArray()['products'][0]));

//$response->debugPrint();

?>