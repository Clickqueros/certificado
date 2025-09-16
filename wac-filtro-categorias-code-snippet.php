<?php
/**
 * WAC Árbol de categorías (3 niveles) — SIEMPRE ABIERTO, padre exclusivo
 * - Si la URL trae hijos/nietos, se marca automáticamente su PADRE (radio)
 * - Oculta el padre con ID 16
 * - URL:
 *    * Solo padre: ?categoria=padre
 *    * Con subcats: ?categoria=hijo;nieto (sin el padre)
 * 
 * OPTIMIZADO PARA CODE SNIPPETS
 */

// === CONFIGURACIÓN INICIAL ===
add_action('init', function () {
  add_rewrite_tag('%categoria%', '([^&]+)');
});

// === FUNCIONES AUXILIARES ===
function wac_get_excluded_ids(){
  $slugs = apply_filters('wac_excluded_parent_slugs', ['productos-sin-categoria','uncategorized']);
  $ids = [];
  foreach ($slugs as $s){
    $t = get_term_by('slug', $s, 'product_cat');
    if ($t && !is_wp_error($t)) $ids[] = (int)$t->term_id;
  }
  $ids[] = 168; // ID explícito a excluir
  return array_unique($ids);
}

function wac_is_excluded_slug($slug){
  $slugs = apply_filters('wac_excluded_parent_slugs', ['productos-sin-categoria','uncategorized']);
  $t = get_term_by('slug', sanitize_title($slug), 'product_cat');
  if ($t && !is_wp_error($t) && (int)$t->term_id === 168) return true;
  return in_array(sanitize_title($slug), $slugs, true);
}

function wac_top_parent_slug_from_slug($slug){
  $term = get_term_by('slug', sanitize_title($slug), 'product_cat');
  if (!$term || is_wp_error($term)) return '';
  if ((int)$term->parent === 0) return $term->slug;
  $anc = get_ancestors($term->term_id, 'product_cat');
  if (empty($anc)) return $term->slug;
  $top_id = end($anc);
  $top = get_term($top_id, 'product_cat');
  return ($top && !is_wp_error($top)) ? $top->slug : $term->slug;
}

// === ESTILOS CSS (MANTENIDOS EXACTAMENTE IGUAL) ===
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

// === SHORTCODE PRINCIPAL ===
add_shortcode('wac_filtro_categorias', function () {
  if (!taxonomy_exists('product_cat')) return '';

  // Procesar URL
  $raw = isset($_GET['categoria']) ? (string) $_GET['categoria'] : '';
  $raw = str_replace('%3B',';',$raw);
  $parts = array_values(array_filter(array_map('sanitize_title', explode(';', $raw))));
  $parts = array_values(array_filter($parts, function($s){ return !wac_is_excluded_slug($s); }));

  // Determinar selecciones
  $selected_parent = '';
  if (!empty($parts)) {
    $selected_parent = wac_top_parent_slug_from_slug($parts[0]);
  }
  $selected_desc = array_values(array_filter($parts, function($s) use ($selected_parent){
    return $s !== $selected_parent;
  }));

  // Obtener categorías padre
  $parents = get_terms([
    'taxonomy'   => 'product_cat',
    'parent'     => 0,
    'hide_empty' => true,
    'orderby'    => 'menu_order',
    'order'      => 'ASC',
    'exclude'    => wac_get_excluded_ids(),
  ]);
  if (is_wp_error($parents) || empty($parents)) return '';

  // Función para renderizar hijos
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

  // Generar HTML
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

    // Toggle
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

    // Aplicar filtro
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
        parts = childSlugs.slice();
      } else if (parentSlug) {
        parts = [parentSlug];
      }

      const excluded = new Set(<?php
        $slugs = apply_filters('wac_excluded_parent_slugs', ['productos-sin-categoria','uncategorized']);
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

    // Eventos
    box.addEventListener('change', function(e){
      const r = e.target.closest('.wac-parent-radio');
      if(r) {
        box.querySelectorAll('.wac-child').forEach(cb=>{
          if(cb.getAttribute('data-top') !== r.value) cb.checked = false;
        });
        applyFilter();
        return;
      }

      if(e.target.matches('.wac-child')) {
        const top = e.target.getAttribute('data-top');
        const pr = box.querySelector('.wac-parent-radio[value="'+top+'"]');
        if (pr && !pr.checked){
          pr.checked = true;
          box.querySelectorAll('.wac-child').forEach(cb=>{
            if(cb.getAttribute('data-top') !== top) cb.checked = false;
          });
        }
        applyFilter();
      }
    });
  })();
  </script>
  <?php
  return ob_get_clean();
});

// === FILTRO DE PRODUCTOS MEJORADO ===
add_action('pre_get_posts', function ($q) {
  if (is_admin() || !$q->is_main_query()) return;
  if (!(function_exists('is_shop') && (is_shop() || is_post_type_archive('product') || is_tax('product_cat')))) return;

  $raw = get_query_var('categoria') ?: ($_GET['categoria'] ?? '');
  if (!$raw) return;

  $raw = str_replace('%3B',';',$raw);
  $parts = array_values(array_filter(array_map('sanitize_title', explode(';', $raw))));
  $parts = array_values(array_filter($parts, function($s){ return !wac_is_excluded_slug($s); }));
  if (empty($parts)) return;

  // LÓGICA MEJORADA DE FILTRADO - MÁS INCLUSIVA
  $all_term_ids = [];
  
  foreach ($parts as $slug) {
    $term = get_term_by('slug', $slug, 'product_cat');
    
    // Búsqueda inteligente si no encuentra por slug
    if (!$term || is_wp_error($term)) {
      $terms_by_name = get_terms([
        'taxonomy' => 'product_cat',
        'name__like' => $slug,
        'hide_empty' => false
      ]);
      
      if (!empty($terms_by_name) && !is_wp_error($terms_by_name)) {
        $term = $terms_by_name[0];
      } else {
        continue;
      }
    }
    
    // Recopilar TODOS los IDs de la jerarquía
    if ($term->parent == 0) {
      // Categoría PADRE: obtener todos sus descendientes
      $all_term_ids[] = $term->term_id;
      
      // Obtener todos los hijos
      $children = get_terms([
        'taxonomy' => 'product_cat',
        'parent' => $term->term_id,
        'hide_empty' => false,
        'fields' => 'ids'
      ]);
      
      if (!is_wp_error($children) && !empty($children)) {
        $all_term_ids = array_merge($all_term_ids, $children);
        
        // Obtener todos los nietos
        foreach ($children as $child_id) {
          $grandchildren = get_terms([
            'taxonomy' => 'product_cat',
            'parent' => $child_id,
            'hide_empty' => false,
            'fields' => 'ids'
          ]);
          
          if (!is_wp_error($grandchildren) && !empty($grandchildren)) {
            $all_term_ids = array_merge($all_term_ids, $grandchildren);
          }
        }
      }
    } else {
      // Categoría HIJO/NIETO: incluir la categoría específica y su jerarquía
      $all_term_ids[] = $term->term_id;
      
      // Incluir padre
      $parent_term = get_term($term->parent, 'product_cat');
      if ($parent_term && !is_wp_error($parent_term)) {
        $all_term_ids[] = $parent_term->term_id;
        
        // Si es nieto, también incluir el hijo intermedio
        if ($parent_term->parent != 0) {
          $grandparent_term = get_term($parent_term->parent, 'product_cat');
          if ($grandparent_term && !is_wp_error($grandparent_term)) {
            $all_term_ids[] = $grandparent_term->term_id;
          }
        }
      }
    }
  }
  
  // Crear consulta única con todos los IDs
  if (!empty($all_term_ids)) {
    $all_term_ids = array_unique($all_term_ids);
    
    // Para debugging - puedes activar esto temporalmente
    // error_log('WAC Filter - Term IDs: ' . print_r($all_term_ids, true));
    
    $tax_query = [[
      'taxonomy' => 'product_cat',
      'field' => 'term_id',
      'terms' => $all_term_ids,
      'operator' => 'IN',
      'include_children' => true // Cambiado a true para incluir productos directamente asignados
    ]];
  }
  
  if (!empty($tax_query)) {
    if (count($tax_query) > 1) {
      $tax_query['relation'] = 'OR';
    }
    $q->set('tax_query', $tax_query);
  }
});

// === OCULTAR CATEGORÍA ID 16 ===
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
