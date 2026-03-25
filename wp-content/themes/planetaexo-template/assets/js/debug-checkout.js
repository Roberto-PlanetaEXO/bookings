/**
 * DEBUG CHECKOUT - Monitor de Mudanças em Tempo Real
 * 
 * Este script monitora tudo que acontece na página e registra no console.
 * Copie os logs do console e me envie.
 */

(function() {
    'use strict';
    
    console.log('%c🔍 DEBUG CHECKOUT ATIVADO', 'background: #00ff00; color: #000; font-size: 20px; padding: 10px;');
    console.log('⏰ Timestamp inicial:', new Date().toISOString());
    
    // Array para armazenar eventos
    window.checkoutDebugLog = [];
    
    function log(tipo, mensagem, dados) {
        const timestamp = new Date().toISOString();
        const entry = {
            timestamp,
            tipo,
            mensagem,
            dados: dados || null
        };
        
        window.checkoutDebugLog.push(entry);
        
        const style = tipo === 'ERRO' ? 'color: red; font-weight: bold;' : 
                     tipo === 'ALERTA' ? 'color: orange; font-weight: bold;' :
                     tipo === 'CSS' ? 'color: blue;' :
                     tipo === 'JS' ? 'color: purple;' :
                     tipo === 'DOM' ? 'color: green;' : '';
        
        console.log(`%c[${tipo}] ${mensagem}`, style, dados || '');
    }
    
    // 1. MONITORAR CARREGAMENTO DE CSS
    log('INICIO', 'Monitorando carregamento de CSS...');
    
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'childList') {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeName === 'LINK' && node.rel === 'stylesheet') {
                        log('CSS', 'Novo CSS carregado: ' + node.href, {
                            href: node.href,
                            media: node.media,
                            id: node.id
                        });
                    }
                    if (node.nodeName === 'STYLE') {
                        log('CSS', 'Novo <style> inline adicionado', {
                            id: node.id,
                            comprimento: node.textContent.length,
                            primeiras100chars: node.textContent.substring(0, 100)
                        });
                    }
                    if (node.nodeName === 'SCRIPT') {
                        log('JS', 'Novo script carregado', {
                            src: node.src || 'inline',
                            async: node.async,
                            defer: node.defer,
                            id: node.id
                        });
                    }
                });
            }
            
            if (mutation.type === 'attributes') {
                if (mutation.attributeName === 'class') {
                    log('DOM', 'Classe alterada no elemento', {
                        elemento: mutation.target.tagName,
                        id: mutation.target.id,
                        classesAntigas: mutation.oldValue,
                        classesNovas: mutation.target.className
                    });
                }
                if (mutation.attributeName === 'style') {
                    log('DOM', 'Style inline alterado', {
                        elemento: mutation.target.tagName,
                        id: mutation.target.id,
                        styleNovo: mutation.target.getAttribute('style')
                    });
                }
            }
        });
    });
    
    // Observar mudanças no <head> e <body>
    observer.observe(document.head, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeOldValue: true,
        attributeFilter: ['class', 'style']
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeOldValue: true,
        attributeFilter: ['class', 'style']
    });
    
    log('INICIO', 'MutationObserver ativado');
    
    // 2. MONITORAR ESTILOS APLICADOS NO ORDER REVIEW
    function checkOrderReview() {
        const orderReview = document.getElementById('order_review');
        if (orderReview) {
            const computed = window.getComputedStyle(orderReview);
            log('CSS', 'Estilos computados do #order_review', {
                display: computed.display,
                flex: computed.flex,
                width: computed.width,
                minWidth: computed.minWidth,
                float: computed.float,
                position: computed.position
            });
        } else {
            log('ALERTA', '#order_review não encontrado');
        }
    }
    
    // 3. MONITORAR FORM CHECKOUT
    function checkFormCheckout() {
        const form = document.querySelector('form.woocommerce-checkout');
        if (form) {
            const computed = window.getComputedStyle(form);
            log('CSS', 'Estilos computados do form.woocommerce-checkout', {
                display: computed.display,
                flexDirection: computed.flexDirection,
                flexWrap: computed.flexWrap,
                gap: computed.gap
            });
        } else {
            log('ALERTA', 'form.woocommerce-checkout não encontrado');
        }
    }
    
    // 4. VERIFICAR ESTRUTURA HTML
    function checkHTMLStructure() {
        log('DOM', 'Verificando estrutura HTML...');
        
        const bodyClasses = document.body.className.split(' ').filter(c => c.includes('checkout'));
        log('DOM', 'Classes do body relacionadas a checkout', bodyClasses);
        
        const wrapper = document.querySelector('.pxo-checkout-classico-wrap');
        log('DOM', 'Wrapper encontrado?', wrapper ? 'SIM' : 'NÃO');
        
        const orderDetailsWrapper = document.querySelector('.order-details-wrapper');
        log('DOM', 'order-details-wrapper encontrado?', orderDetailsWrapper ? 'SIM' : 'NÃO');
        
        const customerDetails = document.getElementById('customer_details');
        log('DOM', 'customer_details encontrado?', customerDetails ? 'SIM' : 'NÃO');
        
        const orderReview = document.getElementById('order_review');
        log('DOM', 'order_review encontrado?', orderReview ? 'SIM' : 'NÃO');
    }
    
    // 5. LISTAR TODOS OS CSS CARREGADOS
    function listAllCSS() {
        log('CSS', 'Listando todos os CSS carregados...');
        const links = document.querySelectorAll('link[rel="stylesheet"]');
        links.forEach((link, index) => {
            log('CSS', `CSS ${index + 1}`, {
                href: link.href,
                media: link.media,
                id: link.id
            });
        });
        
        log('CSS', 'Total de CSS: ' + links.length);
    }
    
    // 6. VERIFICAR CSS INLINE ADICIONADO DEPOIS
    function checkInlineStyles() {
        const styles = document.querySelectorAll('style');
        log('CSS', 'Total de <style> inline: ' + styles.length);
        
        styles.forEach((style, index) => {
            if (style.textContent.includes('woocommerce') || 
                style.textContent.includes('checkout') ||
                style.textContent.includes('order_review')) {
                log('CSS', `<style> ${index + 1} relevante para checkout`, {
                    id: style.id,
                    comprimento: style.textContent.length,
                    contem: [
                        style.textContent.includes('woocommerce') ? 'woocommerce' : null,
                        style.textContent.includes('checkout') ? 'checkout' : null,
                        style.textContent.includes('order_review') ? 'order_review' : null
                    ].filter(Boolean),
                    primeiras200chars: style.textContent.substring(0, 200)
                });
            }
        });
    }
    
    // 7. EXECUTAR VERIFICAÇÕES EM INTERVALOS
    let checkCount = 0;
    const maxChecks = 20; // 10 segundos (500ms x 20)
    
    const interval = setInterval(() => {
        checkCount++;
        log('TIMER', `Verificação #${checkCount} (${checkCount * 500}ms após carregamento)`);
        
        checkHTMLStructure();
        checkOrderReview();
        checkFormCheckout();
        
        if (checkCount >= maxChecks) {
            clearInterval(interval);
            log('FIM', '=== FIM DO MONITORAMENTO ===');
            log('FIM', 'Total de eventos registrados: ' + window.checkoutDebugLog.length);
            console.log('%c📊 RESUMO DO LOG', 'background: #0066cc; color: #fff; font-size: 16px; padding: 5px;');
            console.table(window.checkoutDebugLog);
            
            // Gerar relatório
            console.log('%c📋 COPIE E COLE ESTE RELATÓRIO:', 'background: #ff6600; color: #fff; font-size: 16px; padding: 5px;');
            console.log(JSON.stringify(window.checkoutDebugLog, null, 2));
        }
    }, 500);
    
    // 8. VERIFICAÇÕES INICIAIS
    log('INICIO', '=== VERIFICAÇÕES INICIAIS ===');
    
    setTimeout(() => {
        listAllCSS();
        checkInlineStyles();
        checkHTMLStructure();
        checkOrderReview();
        checkFormCheckout();
    }, 100);
    
    // 9. MONITORAR EVENTOS ESPECÍFICOS DO WOOCOMMERCE
    document.addEventListener('updated_checkout', function() {
        log('EVENTO', 'WooCommerce: updated_checkout disparado');
        checkHTMLStructure();
    });
    
    document.addEventListener('checkout_error', function() {
        log('ERRO', 'WooCommerce: checkout_error disparado');
    });
    
    jQuery(document).on('updated_wc_div', function() {
        log('EVENTO', 'WooCommerce: updated_wc_div disparado');
    });
    
    // 10. CAPTURAR ERROS
    window.addEventListener('error', function(e) {
        log('ERRO', 'JavaScript Error', {
            mensagem: e.message,
            arquivo: e.filename,
            linha: e.lineno,
            coluna: e.colno
        });
    });
    
    log('INICIO', '=== DEBUG CHECKOUT PRONTO ===');
    console.log('%c✅ Para ver o relatório completo, aguarde 10 segundos', 'background: #00cc00; color: #fff; font-size: 14px; padding: 5px;');
    console.log('%c📸 Tire screenshot do console DEPOIS dos 10 segundos', 'background: #cc0000; color: #fff; font-size: 14px; padding: 5px;');
    
})();
