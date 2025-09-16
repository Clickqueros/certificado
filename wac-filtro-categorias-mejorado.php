/**
 * WAC Árbol de categorías (3 niveles) — SIEMPRE ABIERTO, padre exclusivo
 * - Si la URL trae hijos/nietos, se marca automáticamente su PADRE (radio)
 * - Oculta el padre con ID 16
 * - URL:
 *    * Solo padre: ?categoria=padre
 *    * Con subcats: ?categoria=hijo;nieto (sin el padre)
 */
add_action('init', function () {
  add_rewrite_tag('%categoria%', '([^&]+)');
});

/* === CONFIG: slugs a excluir (padres) === */
$EXCLUDED_PARENT_SLUGS = ['productos-sin-categoria','uncategorized'];
function wac_get_excluded_ids(){
  $slugs = apply_filters('wac_excluded_parent_slugs', ['productos-sin-categoria','uncategorized']);
  $ids = [];
  foreach ($slugs as $s){
    $t = get_term_by('slug', $s, 'product_cat');
    if ($t && !is_wp_error($t)) $ids[] = (int)$t->term_id;
  }
  // agrega el ID 168 explícitamente
  $ids[] = 168;
  return array_unique($ids);
}
function wac_is_excluded_slug($slug){
  $slugs = apply_filters('wac_excluded_parent_slugs', ['productos-sin-categoria','uncategorized']);
  $t = get_term_by('slug', sanitize_title($slug), 'product_cat');
  if ($t && !is_wp_error($t) && (int)$t->term_id === 168) return true;
  return in_array(sanitize_title($slug), $slugs, true);
}

/* === Helper: obtener el padre raíz (slug) desde cualquier slug (padre/hijo/nieto) === */
function wac_top_parent_slug_from_slug($slug){
  $term = get_term_by('slug', sanitize_title($slug), 'product_cat');
  if (!$term || is_wp_error($term)) return '';
  if ((int)$term->parent === 0) return $term->slug;
  $anc = get_ancestors($term->term_id, 'product_cat'); // [padre, abuelo, ...]
  if (empty($anc)) return $term->slug;
  $top_id = end($anc);
  $top    = get_term($top_id, 'product_cat');
  return ($top && !is_wp_error($top)) ? $top->slug : $term->slug;
}

/* === Estilos: SIEMPRE ABIERTO === */
add_action('wp_enqueue_scripts', function () {
  $css = '
  .wac-tree{font-size:14px; margin:0 0 1rem 0}
  .wac-tree ul{list-style:none; margin:0; padding:0}
  .wac-tree li{margin:.25rem 0}
  .wac-row{display:flex; align-items:center; gap:.5rem}
  .wac-parent-label, .wac-child-label{color:#b30000; font-weight:600}
  .wac-child-label{font-weight:400}
  .wac-toggle{margin-left:auto; cursor:pointer; user-select:none; font-weight:600}
  .wac-children{margin-left:1.25rem; display:block}
  .wac-parent-radio,.wac-child{margin-right:.35rem}
  ';
  wp_register_style('wac-filter-css', false);
  wp_add_inline_style('wac-filter-css', $css);
  wp_enqueue_style('wac-filter-css');
});

/* === Shortcode === */
add_shortcode('wac_filtro_categorias', function () {
  if (!taxonomy_exists('product_cat')) return '';

  // Selección actual desde la URL
  $raw   = isset($_GET['categoria']) ? (string) $_GET['categoria'] : '';
  $raw   = str_replace('%3B',';',$raw);
  $parts = array_values(array_filter(array_map('sanitize_title', explode(';', $raw))));

  // Quitar excluidos que entren por URL
  $parts = array_values(array_filter($parts, function($s){ return !wac_is_excluded_slug($s); }));

  // === FIX: si la URL trae solo hijos/nietos, calculamos su PADRE raíz y lo marcamos
  $selected_parent = '';
  if (!empty($parts)) {
    $selected_parent = wac_top_parent_slug_from_slug($parts[0]);
  }
  // Descendientes seleccionados (todo lo que no sea el padre raíz):
  $selected_desc = array_values(array_filter($parts, function($s) use ($selected_parent){
    return $s !== $selected_parent;
  }));

  // Padres raíz (excluyendo ID 168 y slugs excluidos)
  $parents = get_terms([
    'taxonomy'   => 'product_cat',
    'parent'     => 0,
    'hide_empty' => true,
    'orderby'    => 'menu_order',
    'order'      => 'ASC',
    'exclude'    => wac_get_excluded_ids(),
  ]);
  if (is_wp_error($parents) || empty($parents)) return '';

  // Pintar hijos/nietos (siempre abiertos)
  $render_children = function ($parent_term, $top_parent_slug, $selected_parent, $selected_desc, $depth=1) use (&$render_children) {
    $children = get_terms([
      'taxonomy'   => 'product_cat',
      'parent'     => $parent_term->term_id,
      'hide_empty' => true,
      'orderby'    => 'menu_order',
      'order'      => 'ASC',
    ]);
    if (is_wp_error($children) || empty($children)) return '';

    $html = '<ul class="wac-children" data-top="'.esc_attr($top_parent_slug).'" data-depth="'.$depth.'">';

    foreach ($children as $child) {
      if (wac_is_excluded_slug($child->slug)) continue;

      $has_grand = get_terms([
        'taxonomy'   => 'product_cat',
        'parent'     => $child->term_id,
        'fields'     => 'ids',
        'number'     => 1,
        'hide_empty' => true
      ]);
      $has_grand = !empty($has_grand);

      $checked = ($selected_parent === $top_parent_slug && in_array($child->slug, $selected_desc, true)) ? ' checked' : '';

      $html .= '<li>';
      $html .=   '<div class="wac-row">';
      $html .=     '<label class="wac-child-label"><input type="checkbox" class="wac-child" data-top="'.esc_attr($top_parent_slug).'" value="'.esc_attr($child->slug).'"'.$checked.'> '.esc_html($child->name).'</label>';
      if ($has_grand) $html .= '<span class="wac-toggle" aria-hidden="true">−</span>';
      $html .=   '</div>';

      if ($has_grand) {
        $html .= $render_children($child, $top_parent_slug, $selected_parent, $selected_desc, $depth+1);
      }
      $html .= '</li>';
    }

    $html .= '</ul>';
    return $html;
  };

  ob_start(); ?>
  <div class="wac-tree" id="wac-filter">
    <ul style="list-style: none !important;">
      <?php foreach ($parents as $p): ?>
        <?php $is_parent = ($selected_parent === $p->slug); ?>
        <li>
          <div class="wac-row">
            <label class="wac-parent-label">
              <input type="radio" name="wac-parent" class="wac-parent-radio" value="<?php echo esc_attr($p->slug); ?>"<?php checked($is_parent); ?>>
              <?php echo esc_html($p->name); ?>
            </label>
            <span class="wac-toggle">−</span>
          </div>
          <?php echo $render_children($p, $p->slug, $selected_parent, $selected_desc, 1); ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <script>
  (function(){
    const box = document.getElementById('wac-filter');
    if(!box) return;

    // Toggle opcional (todo inicia abierto)
    box.addEventListener('click', function(e){
      const tog = e.target.closest('.wac-toggle');
      if(!tog) return;
      const ul = tog.closest('li')?.querySelector(':scope > .wac-children');
      if(ul){
        const isOpen = ul.style.display !== 'none';
        ul.style.display = isOpen ? 'none' : 'block';
        tog.textContent = isOpen ? '+' : '−';
      }
    });

    // Base URL
    const current = new URL(window.location.href);
    const base = location.origin + location.pathname;

    function applyFilter(){
      const parent = box.querySelector('.wac-parent-radio:checked');
      const parentSlug = parent ? parent.value : '';
      const childSlugs = [];
      box.querySelectorAll('.wac-child:checked').forEach(cb => childSlugs.push(cb.value));

      const next = new URL(base, window.location.origin);
      let parts = [];

      if (childSlugs.length > 0) {
        parts = childSlugs.slice(); // hijos/nietos sin el padre
      } else if (parentSlug) {
        parts = [parentSlug];       // solo padre
      }

      // limpiar excluidos por si acaso
      const excluded = new Set(<?php
        $slugs = apply_filters('wac_excluded_parent_slugs', ['productos-sin-categoria','uncategorized']);
        // añade slug de ID 168
        $t168 = get_term(168, 'product_cat');
        if ($t168 && !is_wp_error($t168)) $slugs[] = $t168->slug;
        echo json_encode(array_values(array_map('sanitize_title',$slugs)));
      ?>);
      parts = parts.filter(p => !excluded.has(p));

      if (parts.length) next.searchParams.set('categoria', parts.join(';'));
      ['orderby','order','paged','s'].forEach(k=>{
        if(current.searchParams.has(k)) next.searchParams.set(k, current.searchParams.get(k));
      });

      window.location.href = next.toString();
    }

    // Cambio de padre: exclusivo (no colapsamos)
    box.addEventListener('change', function(e){
      const r = e.target.closest('.wac-parent-radio');
      if(!r) return;
      box.querySelectorAll('.wac-child').forEach(cb=>{
        if(cb.getAttribute('data-top') !== r.value) cb.checked = false;
      });
      applyFilter();
    });

    // Hijos/Nietos: activan su padre si no lo está
    box.addEventListener('change', function(e){
      if(!e.target.matches('.wac-child')) return;
      const top = e.target.getAttribute('data-top');
      const pr  = box.querySelector('.wac-parent-radio[value="'+top+'"]');
      if (pr && !pr.checked){
        pr.checked = true;
        box.querySelectorAll('.wac-child').forEach(cb=>{
          if(cb.getAttribute('data-top') !== top) cb.checked = false;
        });
      }
      applyFilter();
    });
  })();
  </script>
  <?php
  return ob_get_clean();
});

/* === Aplica el filtro al loop de WooCommerce === */
add_action('pre_get_posts', function ($q) {
  if (is_admin() || !$q->is_main_query()) return;
  if (!(function_exists('is_shop') && (is_shop() || is_post_type_archive('product') || is_tax('product_cat')))) return;

  $raw = get_query_var('categoria') ?: ($_GET['categoria'] ?? '');
  if (!$raw) return;

  $raw   = str_replace('%3B',';',$raw);
  $parts = array_values(array_filter(array_map('sanitize_title', explode(';', $raw))));
  // Limpia excluidos
  $parts = array_values(array_filter($parts, function($s){ return !wac_is_excluded_slug($s); }));
  if (empty($parts)) return;

  // === MEJORA: Lógica mejorada para manejar categorías padre e hijo ===
  $tax_query = [];
  
  foreach ($parts as $slug) {
    $term = get_term_by('slug', $slug, 'product_cat');
    if (!$term || is_wp_error($term)) {
      // Si no encuentra por slug, intentar buscar por nombre parcial
      $terms_by_name = get_terms([
        'taxonomy' => 'product_cat',
        'name__like' => $slug,
        'hide_empty' => false
      ]);
      
      if (!empty($terms_by_name) && !is_wp_error($terms_by_name)) {
        $term = $terms_by_name[0]; // Tomar el primer resultado
      } else {
        continue; // Si no encuentra nada, saltar
      }
    }
    
    // Si es una categoría padre, incluir todos sus hijos
    if ($term->parent == 0) {
      $tax_query[] = [
        'taxonomy' => 'product_cat',
        'field' => 'term_id',
        'terms' => $term->term_id,
        'operator' => 'IN',
        'include_children' => true // Incluir productos de subcategorías
      ];
    } else {
      // Si es una categoría hijo/nieto, incluir también su padre
      $parent_term = get_term($term->parent, 'product_cat');
      if ($parent_term && !is_wp_error($parent_term)) {
        // Incluir tanto el hijo como el padre
        $tax_query[] = [
          'taxonomy' => 'product_cat',
          'field' => 'term_id',
          'terms' => [$term->term_id, $parent_term->term_id],
          'operator' => 'IN',
          'include_children' => true
        ];
      } else {
        // Fallback: solo la categoría hijo
        $tax_query[] = [
          'taxonomy' => 'product_cat',
          'field' => 'term_id',
          'terms' => $term->term_id,
          'operator' => 'IN',
          'include_children' => true
        ];
      }
    }
  }
  
  if (!empty($tax_query)) {
    // Si hay múltiples categorías, usar OR para que aparezcan productos de cualquiera
    if (count($tax_query) > 1) {
      $tax_query['relation'] = 'OR';
    }
    $q->set('tax_query', $tax_query);
  }
});

/* Ocultar categoría por ID en todos los listados de product_cat */
add_filter('get_terms', function($terms, $taxonomies, $args) {
    if (in_array('product_cat', (array) $taxonomies, true)) {
        foreach ($terms as $key => $term) {
            if ((int)$term->term_id === 16) {
                unset($terms[$key]);
            }
        }
        $terms = array_values($terms);
    }
    return $terms;
}, 10, 3);
