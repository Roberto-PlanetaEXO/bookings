<?php
/**
 * Análise: Estrutura de Proposta vs Campos ACF Disponíveis
 * 
 * Este arquivo compara a estrutura ideal (baseada nos prints)
 * com os campos ACF que já foram importados
 */

// Se acessado diretamente, carrega o WordPress
if (!defined('ABSPATH')) {
    require_once dirname(__FILE__) . '/../../../../wp-load.php';
}

if (!current_user_can('manage_options')) {
    wp_die('Acesso negado');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Análise: Estrutura Proposta vs ACF</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; }
        .comparison { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 20px 0; }
        .section { background: white; padding: 15px; border-radius: 5px; border-left: 4px solid #0073aa; }
        .section h3 { margin-top: 0; color: #0073aa; }
        .needed { color: #d32f2f; font-weight: bold; }
        .exists { color: #388e3c; font-weight: bold; }
        .warning { background: #fff3cd; padding: 10px; border-radius: 3px; margin: 10px 0; border-left: 4px solid #ffc107; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background: #f0f0f0; padding: 8px; text-align: left; font-weight: bold; }
        td { padding: 8px; border-bottom: 1px solid #ddd; }
        .status-yes { color: #388e3c; }
        .status-no { color: #d32f2f; }
        .type-badge { background: #e3f2fd; padding: 2px 6px; border-radius: 3px; font-size: 12px; }
    </style>
</head>
<body>
<div class="container">
    <h1>📋 Análise: Estrutura de Proposta vs Campos ACF</h1>
    
    <div class="warning">
        <strong>ℹ️ Como usar:</strong> Este arquivo mapeia a estrutura da proposta que você mostrou nos prints
        com os campos ACF que já foram importados. Assim conseguimos ver o que falta.
    </div>
    
    <?php
    
    // Define a estrutura esperada (baseada nos prints)
    $expected_structure = array(
        'header' => array(
            'title' => 'Header & Hero',
            'fields' => array(
                'tour_title' => array('label' => 'Título do Tour', 'type' => 'text', 'required' => true),
                'hero_image' => array('label' => 'Imagem Hero', 'type' => 'image', 'required' => true),
                'guest_names' => array('label' => 'Nomes dos Hóspedes', 'type' => 'text', 'required' => true),
            )
        ),
        'introduction' => array(
            'title' => 'Introdução',
            'fields' => array(
                'greeting' => array('label' => 'Saudação (Dear...)', 'type' => 'text', 'required' => true),
                'offer_description' => array('label' => 'Descrição da Oferta', 'type' => 'textarea', 'required' => true),
                'important_note' => array('label' => 'Nota Importante', 'type' => 'text', 'required' => false),
            )
        ),
        'product' => array(
            'title' => 'Informações do Produto',
            'fields' => array(
                'product_name' => array('label' => 'Nome do Produto/Tour', 'type' => 'text', 'required' => true),
                'start_date' => array('label' => 'Data de Início', 'type' => 'date', 'required' => true),
            )
        ),
        'itinerary' => array(
            'title' => 'Itinerário',
            'fields' => array(
                'itinerary_days' => array('label' => 'Dias do Itinerário (Repeater)', 'type' => 'repeater', 'required' => true),
                '  └─ day_number' => array('label' => 'Número do Dia', 'type' => 'text', 'required' => true),
                '  └─ day_title' => array('label' => 'Título do Dia', 'type' => 'text', 'required' => true),
                '  └─ day_description' => array('label' => 'Descrição (Rich Text)', 'type' => 'wysiwyg', 'required' => true),
            )
        ),
        'pricing' => array(
            'title' => 'Preço & Quantidade',
            'fields' => array(
                'quantity' => array('label' => 'Quantidade de Pessoas', 'type' => 'number', 'required' => true),
                'price_per_person' => array('label' => 'Preço por Pessoa', 'type' => 'number', 'required' => true),
                'total_price' => array('label' => 'Preço Total (calculado)', 'type' => 'number', 'required' => true),
            )
        ),
        'inclusions' => array(
            'title' => 'Inclusões & Exclusões',
            'fields' => array(
                'what_included' => array('label' => 'O que está incluído', 'type' => 'repeater', 'required' => true),
                '  └─ item' => array('label' => 'Item Incluído', 'type' => 'text', 'required' => true),
                'what_not_included' => array('label' => 'O que NÃO está incluído', 'type' => 'repeater', 'required' => true),
                '  └─ item' => array('label' => 'Item Não Incluído', 'type' => 'text', 'required' => true),
            )
        ),
        'policies' => array(
            'title' => 'Políticas',
            'fields' => array(
                'cancellation_policy' => array('label' => 'Política de Cancelamento', 'type' => 'wysiwyg', 'required' => true),
            )
        ),
        'coupon' => array(
            'title' => 'Cupom de Desconto',
            'fields' => array(
                'coupon_code' => array('label' => 'Código do Cupom', 'type' => 'text', 'required' => false),
            )
        ),
        'agent' => array(
            'title' => 'Agente de Viagem (Sidebar)',
            'fields' => array(
                'agent_name' => array('label' => 'Nome do Agente', 'type' => 'text', 'required' => true),
                'agent_photo' => array('label' => 'Foto do Agente', 'type' => 'image', 'required' => true),
                'agent_whatsapp' => array('label' => 'WhatsApp (Número)', 'type' => 'text', 'required' => true),
                'agent_email' => array('label' => 'Email', 'type' => 'email', 'required' => true),
                'agent_phone' => array('label' => 'Telefone', 'type' => 'text', 'required' => false),
            )
        ),
    );
    
    // Busca campos ACF realmente importados
    $acf_fields_available = array();
    
    if (function_exists('acf_get_field_groups')) {
        $field_groups = acf_get_field_groups();
        $excluded = array('Agentes (Usuários)', 'Informações da Proposta', 'Integração TrustPilot');
        
        foreach ($field_groups as $group) {
            if (in_array($group['title'], $excluded)) {
                continue;
            }
            
            $fields = acf_get_fields($group['ID']);
            if ($fields) {
                foreach ($fields as $field) {
                    $acf_fields_available[$field['name']] = array(
                        'label' => $field['label'],
                        'type' => $field['type'],
                        'group' => $group['title'],
                        'required' => isset($field['required']) ? $field['required'] : false
                    );
                }
            }
        }
    }
    
    // Análise comparativa
    echo '<h2>📊 Análise Comparativa por Seção</h2>';
    
    $total_expected = 0;
    $total_found = 0;
    $missing_fields = array();
    
    foreach ($expected_structure as $section_key => $section) {
        echo '<div style="background: white; padding: 15px; border-radius: 5px; margin: 15px 0; border-left: 4px solid #0073aa;">';
        echo '<h3>' . $section['title'] . '</h3>';
        
        echo '<table>';
        echo '<tr><th>Campo Esperado</th><th>Type</th><th>Status</th><th>Campo ACF</th></tr>';
        
        foreach ($section['fields'] as $field_key => $field_info) {
            $total_expected++;
            
            // Remove o prefixo de indentação se houver
            $search_key = ltrim($field_key, ' └─');
            
            // Procura por campo similar no ACF
            $found = false;
            $found_field = null;
            
            foreach ($acf_fields_available as $acf_name => $acf_info) {
                // Match por similaridade
                if (stripos($acf_name, $search_key) !== false || 
                    stripos($search_key, $acf_name) !== false ||
                    strtolower(str_replace('_', '', $acf_name)) === strtolower(str_replace('_', '', $search_key))) {
                    $found = true;
                    $found_field = $acf_info;
                    $total_found++;
                    break;
                }
            }
            
            if (!$found) {
                $missing_fields[$section['title']][] = $field_key;
            }
            
            $status = $found ? '<span class="status-yes">✓ EXISTE</span>' : '<span class="status-no">✗ FALTA</span>';
            
            $field_display = $field_key;
            if (strpos($field_key, '└─') !== false) {
                $field_display = '&nbsp;&nbsp;&nbsp;' . $field_display;
            }
            
            echo '<tr>';
            echo '<td>' . $field_display . '</td>';
            echo '<td><span class="type-badge">' . $field_info['type'] . '</span></td>';
            echo '<td>' . $status . '</td>';
            echo '<td>';
            if ($found_field) {
                echo '<strong>' . $found_field['label'] . '</strong><br>';
                echo '<small style="color: #999;">(' . $found_field['group'] . ')</small>';
            } else {
                echo '-';
            }
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
        echo '</div>';
    }
    
    // Resumo
    echo '<div style="background: white; padding: 20px; border-radius: 5px; margin: 20px 0; border: 2px solid #0073aa;">';
    echo '<h2>📌 Resumo</h2>';
    echo '<p><strong>Total esperado:</strong> ' . $total_expected . ' campos</p>';
    echo '<p><strong>Encontrados:</strong> <span class="status-yes">' . $total_found . '</span></p>';
    echo '<p><strong>Faltando:</strong> <span class="status-no">' . ($total_expected - $total_found) . '</span></p>';
    echo '<p><strong>Cobertura:</strong> ' . round(($total_found / $total_expected) * 100, 1) . '%</p>';
    
    if (count($missing_fields) > 0) {
        echo '<h3>Campos que Faltam:</h3>';
        echo '<ul>';
        foreach ($missing_fields as $section => $fields) {
            echo '<li><strong>' . $section . ':</strong> ' . implode(', ', $fields) . '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p style="color: #388e3c;"><strong>✓ Todos os campos necessários foram encontrados!</strong></p>';
    }
    
    echo '</div>';
    
    // Campos ACF não mapeados
    echo '<h2>🔍 Campos ACF Não Mapeados (possível usar em outro lugar)</h2>';
    echo '<table>';
    echo '<tr><th>Nome do Campo</th><th>Type</th><th>Group</th><th>Obrigatório</th></tr>';
    
    $unmapped = 0;
    foreach ($acf_fields_available as $name => $info) {
        $found_in_expected = false;
        
        foreach ($expected_structure as $section) {
            foreach ($section['fields'] as $field_key => $field_info) {
                $search_key = ltrim($field_key, ' └─');
                if ($name === $search_key || 
                    stripos($name, $search_key) !== false || 
                    stripos($search_key, $name) !== false) {
                    $found_in_expected = true;
                    break 2;
                }
            }
        }
        
        if (!$found_in_expected) {
            $unmapped++;
            $req = $info['required'] ? '✓ Sim' : 'Não';
            echo '<tr>';
            echo '<td><code>' . $name . '</code></td>';
            echo '<td><span class="type-badge">' . $info['type'] . '</span></td>';
            echo '<td>' . $info['group'] . '</td>';
            echo '<td>' . $req . '</td>';
            echo '</tr>';
        }
    }
    
    if ($unmapped === 0) {
        echo '<tr><td colspan="4" style="color: #999; text-align: center;">Nenhum campo não mapeado</td></tr>';
    }
    
    echo '</table>';
    
    ?>
</div>
</body>
</html>
