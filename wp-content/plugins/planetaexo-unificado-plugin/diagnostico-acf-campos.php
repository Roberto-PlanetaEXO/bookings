<?php
/**
 * Diagnóstico: Mapear todos os campos ACF importados
 * Coloque na raiz do site ou acesse como: wp-admin/admin.php?page=planetaexo-diagnostico
 * 
 * Este arquivo analisa quais campos estão configurados nas field groups
 * para ajudar no mapeamento da proposta
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
    <title>Diagnóstico ACF - Mapeamento de Campos</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #333; border-bottom: 3px solid #0073aa; padding-bottom: 10px; }
        h2 { color: #0073aa; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; background: white; margin: 15px 0; }
        th { background: #0073aa; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        .group-name { font-weight: bold; color: #0073aa; }
        .field-type { background: #e7f3ff; padding: 3px 8px; border-radius: 3px; font-size: 12px; }
        .required { color: red; font-weight: bold; }
        .excluded { opacity: 0.5; background: #fff3cd; }
        .summary { background: white; padding: 15px; border-radius: 5px; margin: 20px 0; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
<div class="container">
    <h1>📊 Diagnóstico ACF - Mapeamento de Campos</h1>
    
    <?php
    
    if (!function_exists('acf_get_field_groups')) {
        echo '<p style="color: red;"><strong>❌ ACF não está ativado</strong></p>';
        exit;
    }
    
    $excluded_groups = array(
        'Agentes (Usuários)',
        'Informações da Proposta',
        'Integração TrustPilot'
    );
    
    // Busca todos os field groups
    $all_field_groups = acf_get_field_groups();
    
    echo '<div class="summary">';
    echo '<strong>Total de Field Groups:</strong> ' . count($all_field_groups) . '<br>';
    echo '<strong>Groups Excluídos:</strong> ' . count($excluded_groups) . '<br>';
    echo '<strong>Groups Ativos:</strong> ' . (count($all_field_groups) - count($excluded_groups)) . '<br>';
    echo '</div>';
    
    if (empty($all_field_groups)) {
        echo '<p style="color: red;"><strong>❌ Nenhum field group encontrado</strong></p>';
        exit;
    }
    
    $total_fields = 0;
    $fields_by_group = array();
    
    echo '<h2>📋 Field Groups Importados</h2>';
    echo '<table>';
    echo '<tr><th>Nome do Group</th><th>Localização</th><th>Número de Fields</th></tr>';
    
    foreach ($all_field_groups as $group) {
        $is_excluded = in_array($group['title'], $excluded_groups);
        $class = $is_excluded ? 'excluded' : '';
        
        $fields = acf_get_fields($group['ID']);
        $field_count = !empty($fields) ? count($fields) : 0;
        $total_fields += $field_count;
        
        // Guarda para próxima tabela
        if (!$is_excluded) {
            $fields_by_group[$group['ID']] = array(
                'title' => $group['title'],
                'fields' => $fields
            );
        }
        
        // Deduz location
        $location_str = 'Não configurada';
        if (!empty($group['location'])) {
            $locations = array();
            foreach ($group['location'] as $or_group) {
                foreach ($or_group as $rule) {
                    $locations[] = $rule['param'] . ' = ' . $rule['value'];
                }
            }
            $location_str = implode(' | ', array_slice($locations, 0, 2));
        }
        
        echo '<tr class="' . $class . '">';
        echo '<td class="group-name">' . $group['title'] . '</td>';
        echo '<td>' . $location_str . '</td>';
        echo '<td><strong>' . $field_count . '</strong> fields</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    
    echo '<div class="summary">';
    echo '<strong>Total de Fields (excludentes excluídos):</strong> ' . $total_fields;
    echo '</div>';
    
    // Agora lista os campos de cada group
    echo '<h2>🔍 Detalhes dos Fields por Group</h2>';
    
    foreach ($fields_by_group as $group_id => $group_data) {
        echo '<h3>' . $group_data['title'] . '</h3>';
        
        if (empty($group_data['fields'])) {
            echo '<p style="color: #999;">Nenhum field neste group</p>';
            continue;
        }
        
        echo '<table>';
        echo '<tr>';
        echo '<th>Nome do Field</th>';
        echo '<th>Type</th>';
        echo '<th>Nome Técnico</th>';
        echo '<th>Obrigatório</th>';
        echo '<th>Instruções</th>';
        echo '</tr>';
        
        foreach ($group_data['fields'] as $field) {
            $required = isset($field['required']) && $field['required'] ? '<span class="required">✓ SIM</span>' : 'Não';
            $instructions = !empty($field['instructions']) ? substr($field['instructions'], 0, 50) . '...' : '-';
            
            echo '<tr>';
            echo '<td><strong>' . $field['label'] . '</strong></td>';
            echo '<td><span class="field-type">' . $field['type'] . '</span></td>';
            echo '<td><code>' . $field['name'] . '</code></td>';
            echo '<td>' . $required . '</td>';
            echo '<td>' . $instructions . '</td>';
            echo '</tr>';
        }
        
        echo '</table>';
    }
    
    // Resumo para mapeamento
    echo '<h2>📝 Resumo para Mapeamento de Proposta</h2>';
    
    $field_map = array();
    foreach ($fields_by_group as $group_id => $group_data) {
        foreach ($group_data['fields'] as $field) {
            $field_map[$field['name']] = array(
                'label' => $field['label'],
                'type' => $field['type'],
                'group' => $group_data['title'],
                'required' => isset($field['required']) ? $field['required'] : false
            );
        }
    }
    
    echo '<div class="summary">';
    echo '<h3>Campos Disponíveis (ordem alfabética)</h3>';
    echo '<pre style="background: #f4f4f4; padding: 10px; border-radius: 3px; overflow-x: auto;">';
    
    ksort($field_map);
    foreach ($field_map as $name => $info) {
        $req = $info['required'] ? '[OBRIG]' : '';
        echo sprintf("%-40s | %-15s | %s %s\n", 
            $name, 
            $info['type'],
            $info['group'],
            $req
        );
    }
    
    echo '</pre>';
    echo '</div>';
    
    // Próximos passos
    echo '<h2>✅ Próximos Passos</h2>';
    echo '<ol>';
    echo '<li>Revisar os campos acima e conferir com a estrutura da proposta</li>';
    echo '<li>Identificar quais campos correspondem a cada seção da proposta</li>';
    echo '<li>Criar ou ajustar fields que faltarem</li>';
    echo '<li>Criar template customizado para exibir a proposta no front-end</li>';
    echo '</ol>';
    
    ?>
</div>
</body>
</html>
