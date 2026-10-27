<?php
$productCount = count($products);
$previous = $products[($currentIndex - 1 + $productCount) % $productCount];
$next = $products[($currentIndex + 1) % $productCount];
$categoryName = 'Меню';
foreach ($catalog['categories'] ?? [] as $category) {
    if (($category['id'] ?? '') === ($product['category'] ?? '')) {$categoryName = (string)($category['name'] ?? 'Меню'); break;}
}
$currentPrice = active_product_price($product);
$regularPrice = $product['price_minor'] ?? null;
$options = product_options($product);
$optionMode = ($product['option_mode'] ?? 'multi') === 'single' ? 'single' : 'multi';
$optionsRequired = ($product['options_required'] ?? false) === true;
$payload = product_cart_payload($product, $products);
$description = trim((string)($product['description'] ?? ''));
$ingredients = trim((string)($product['ingredients'] ?? ''));
$productPath = product_static_url($product);
page_start($product['name'].' - Sushi Relax', $description !== '' ? $description : $product['name'].' у меню Sushi Relax. Фото, ціна та оформлення замовлення онлайн.', 'product-mode', $productPath);
?>
<main id="content" class="product-page">
  <nav class="product-breadcrumbs" aria-label="Навігаційний шлях"><a href="/#menu">← Меню</a><span aria-hidden="true">/</span><span><?=h($categoryName)?></span></nav>
  <article class="product-detail" aria-labelledby="product-title" data-product data-product-id="<?=h($product['id'])?>">
    <figure class="product-detail-media"><button class="product-detail-image-button" type="button" data-product-image-open aria-label="Відкрити фото <?=h($product['name'])?> у повному розмірі"><img src="<?=h(product_image($product))?>" alt="<?=h($product['name'])?>" width="1200" height="900"></button></figure>
    <dialog class="product-image-lightbox" data-product-image-lightbox aria-label="Фото <?=h($product['name'])?> у повному розмірі"><button class="product-image-lightbox-close" type="button" data-product-image-close aria-label="Закрити фото">×</button><img src="<?=h(product_image($product))?>" alt="<?=h($product['name'])?>"></dialog>
    <div class="product-detail-content"><p class="eyebrow"><?=h($categoryName)?></p><?php if(!empty($product['badge'])):?><span class="badge product-detail-badge"><?=h($product['badge'])?></span><?php endif;?><h1 id="product-title"><?=h($product['name'])?></h1><?php if($description!==''):?><p class="product-detail-description"><?=h($description)?></p><?php endif;?>
      <dl class="product-detail-meta"><div><dt>Склад</dt><dd><?php if($ingredients!==''): ingredient_list($ingredients); else: ?>Уточнюється<?php endif; ?></dd></div><div><dt>Вага</dt><dd><?=is_int($product['weight_g']??null)?(int)$product['weight_g'].' г':'Уточнюється'?></dd></div><?php if(is_int($product['prep_minutes']??null)):?><div><dt>Час приготування</dt><dd>≈ <?=(int)$product['prep_minutes']?> хв</dd></div><?php endif;?></dl>
      <?php if($options):?><fieldset class="product-options product-detail-options" data-product-options<?= $optionsRequired ? ' data-product-options-required' : '' ?>><legend><?=h((string)($product['option_label']??'Додатково'))?></legend><?php foreach($options as $option):?><label><input type="<?=$optionMode==='single'?'radio':'checkbox'?>"<?= $optionMode === 'single' ? ' name="product-option-' . h((string)$product['id']) . '"' : '' ?> data-product-option value="<?=h($option['id'])?>" data-name="<?=h($option['name'])?>" data-price="<?=(int)$option['price_minor']?>"><span><?=h($option['name'])?><?= $option['price_minor'] > 0 ? ' · +' . h(money($option['price_minor'])) : '' ?></span></label><?php endforeach;?><?php if($optionsRequired):?><small class="product-option-error" data-product-option-error hidden>Оберіть один соус.</small><?php endif;?></fieldset><?php endif;?>
      <footer class="product-detail-buy"><strong data-price-display><?php if(is_int($currentPrice)&&is_int($regularPrice)&&$currentPrice<$regularPrice):?><del><?=h(money($regularPrice))?></del> <ins><?=h(money($currentPrice))?></ins><?php else:?><?=h(money($currentPrice))?><?php endif;?></strong><div class="product-actions"><div class="qty product-qty" aria-label="Кількість <?=h($product['name'])?>"><button type="button" data-product-qty="minus" aria-label="Зменшити кількість">−</button><span data-product-quantity aria-live="polite">1</span><button type="button" data-product-qty="plus" aria-label="Збільшити кількість">+</button></div><button type="button" class="button" data-add-product='<?=h(json_encode($payload,JSON_UNESCAPED_UNICODE))?>' <?=is_int($currentPrice)?'':'disabled'?>>Додати в кошик</button></div></footer>
    </div>
  </article>
  <a class="product-page-arrow product-page-arrow--previous" href="<?=h(product_static_url($previous))?>" data-product-previous aria-label="Попередня позиція: <?=h($previous['name'])?>" title="<?=h($previous['name'])?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg></a>
  <a class="product-page-arrow product-page-arrow--next" href="<?=h(product_static_url($next))?>" data-product-next aria-label="Наступна позиція: <?=h($next['name'])?>" title="<?=h($next['name'])?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></a>
  <nav class="product-neighbor-links" aria-label="Сусідні позиції"><a href="<?=h(product_static_url($previous))?>"><small>Попередня</small><strong><?=h($previous['name'])?></strong></a><a href="<?=h(product_static_url($next))?>"><small>Наступна</small><strong><?=h($next['name'])?></strong></a></nav>
</main>
<?php page_end(true); ?>
