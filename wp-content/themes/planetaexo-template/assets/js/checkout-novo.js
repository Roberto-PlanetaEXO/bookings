/**
 * Checkout Novo - Ajuste de largura dos botões de pagamento
 * Aplica width: 90% aos botões Apple Pay / Google Pay injetados pelo Stripe
 */

(function() {
    'use strict';
    
    function adjustPaymentButtons() {
        // Lista de todos os seletores possíveis
        const selectors = [
            '.gpay-card-info-container-fill',
            '#google-button-container',
            '.gpay-card-info-container',
            'button[aria-label="Apple Pay"]',
            '.apple-pay-button',
            '.payment-request-button',
            '#wc-stripe-express-checkout-element iframe',
            '#wc-stripe-express-checkout-element > div'
        ];
        
        let modified = false;
        
        selectors.forEach(function(selector) {
            const elements = document.querySelectorAll(selector);
            elements.forEach(function(el) {
                if (el) {
                    el.style.setProperty('width', '90%', 'important');
                    el.style.setProperty('max-width', '90%', 'important');
                    modified = true;
                }
            });
        });
        
        // Ajusta também qualquer div dentro do container principal
        const mainContainer = document.querySelector('#wc-stripe-express-checkout-element');
        if (mainContainer) {
            const allDivs = mainContainer.querySelectorAll('div');
            allDivs.forEach(function(div) {
                // Só aplica se tiver width: 100% ou não tiver width definida
                const currentWidth = window.getComputedStyle(div).width;
                if (div.style.width === '100%' || currentWidth === mainContainer.offsetWidth + 'px') {
                    div.style.setProperty('width', '90%', 'important');
                    div.style.setProperty('max-width', '90%', 'important');
                    modified = true;
                }
            });
        }
        
        return modified;
    }
    
    // Executa imediatamente
    adjustPaymentButtons();
    
    // Executa quando DOM carregar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', adjustPaymentButtons);
    }
    
    // MutationObserver para detectar quando elementos são injetados
    const observer = new MutationObserver(function(mutations) {
        adjustPaymentButtons();
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Executa periodicamente (a cada 500ms nos primeiros 10s)
    let attempts = 0;
    const maxAttempts = 20; // 20 × 500ms = 10 segundos
    
    const interval = setInterval(function() {
        const modified = adjustPaymentButtons();
        attempts++;
        
        // Para de tentar após 10s ou quando encontrar e modificar os elementos
        if (attempts >= maxAttempts || (modified && attempts >= 5)) {
            clearInterval(interval);
        }
    }, 500);
    
    // Executa também nos eventos do WooCommerce
    jQuery(document.body).on('updated_checkout payment_method_selected', function() {
        setTimeout(adjustPaymentButtons, 100);
        setTimeout(adjustPaymentButtons, 500);
        setTimeout(adjustPaymentButtons, 1000);
    });
    
    // ════════════════════════════════════════════════════════
    // FORÇA BOOKING SUMMARY / TABLE 100% WIDTH
    // ════════════════════════════════════════════════════════
    function forceOrderReviewFullWidth() {
        const orderReview = document.getElementById('order_review');
        if (!orderReview) return;
        orderReview.style.width = '100%';
        orderReview.style.minWidth = '0';
        const wrapper = orderReview.querySelector('.order-details-wrapper');
        if (wrapper) {
            wrapper.style.width = '100%';
            wrapper.style.maxWidth = '100%';
        }
        const table = orderReview.querySelector('table.shop_table, .woocommerce-checkout-review-order-table');
        if (table) {
            table.style.width = '100%';
            table.style.maxWidth = '100%';
        }
    }
    
    // ════════════════════════════════════════════════════════
    // ADICIONA TÍTULO "BOOKING SUMMARY" NO ORDER REVIEW
    // ════════════════════════════════════════════════════════
    function addBookingSummaryTitle() {
        if (document.querySelector('#order_review .booking-summary-title')) return;
        const orderReview = document.querySelector('#order_review');
        const table = orderReview ? orderReview.querySelector('.shop_table') : null;
        if (!table || !table.parentNode || !document.body.contains(table)) return;

        try {
            const title = document.createElement('h3');
            title.className = 'booking-summary-title';
            title.textContent = 'Booking Summary';
            table.parentNode.insertBefore(title, table);
        } catch (e) {
            console.warn('checkout-novo: addBookingSummaryTitle', e);
        }
    }
    
    // Executa as funções
    addBookingSummaryTitle();
    forceOrderReviewFullWidth();
    
    // Executa quando DOM carregar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            addBookingSummaryTitle();
            forceOrderReviewFullWidth();
        });
    }
    
    // Executa nos eventos do WooCommerce (AJAX refresh)
    jQuery(document.body).on('updated_checkout', function() {
        setTimeout(function() {
            addBookingSummaryTitle();
            forceOrderReviewFullWidth();
        }, 100);
    });
    
    // ════════════════════════════════════════════════════════
    // FORÇA SELECT2 ABRIR SEMPRE PARA BAIXO
    // ════════════════════════════════════════════════════════
    function forceSelect2DropdownBelow() {
        // Remove a classe que indica dropdown acima
        jQuery('.select2-container--above').removeClass('select2-container--above').addClass('select2-container--below');
        
        // Força o dropdown aparecer abaixo
        jQuery('.select2-dropdown--above').removeClass('select2-dropdown--above').addClass('select2-dropdown--below');
    }
    
    // Intercepta a abertura do Select2
    jQuery(document).on('select2:open', function(e) {
        setTimeout(function() {
            forceSelect2DropdownBelow();
        }, 10);
    });
    
    // Executa periodicamente para remover classe --above se aparecer
    setInterval(forceSelect2DropdownBelow, 100);
    
    // Executa também no updated_checkout
    jQuery(document.body).on('updated_checkout', function() {
        setTimeout(forceSelect2DropdownBelow, 100);
    });
    
    // ════════════════════════════════════════════════════════
    // CPF / PASSPORT LOGIC BASEADO NO PAÍS
    // Ordem: Country primeiro, depois CPF ou Passport
    // Reaplica após QUALQUER alteração no DOM (updated_checkout, etc)
    // ════════════════════════════════════════════════════════
    let lastCpfPassportRun = 0;
    const CPF_PASSPORT_DEBOUNCE = 300;
    
    let cpfPassportObserver = null;
    let billingWrapperEl = null;

    /**
     * Campo de passaporte é clonado do CPF: remove pattern/maxlength/máscara e classes de validação de CPF.
     * Sem isso o browser ou plugins bloqueiam o envio (mesmas regras do CPF).
     */
    function sanitizePassportInput($input) {
        if (!$input || !$input.length) {
            return;
        }
        if (typeof $input.unmask === 'function') {
            try { $input.unmask(); } catch (e) {}
        }
        $input.removeAttr('pattern').removeAttr('maxlength').removeAttr('data-mask').removeAttr('data-rule-cpf')
            .removeAttr('data-validate-cpf').removeAttr('title');
        var el = $input[0];
        if (el) {
            el.removeAttribute('pattern');
            el.removeAttribute('maxlength');
            el.setAttribute('type', 'text');
            el.setAttribute('autocomplete', 'off');
            el.setAttribute('inputmode', 'text');
            el.className = (el.className || '')
                .replace(/\bcpf\b/gi, '')
                .replace(/\bwc[_-]?cpf\b/gi, '')
                .replace(/\bvalidate-[a-z0-9_-]+\b/gi, '')
                .replace(/\s+/g, ' ')
                .trim();
        }
    }
    
    function handleCpfPassportFields() {
        const now = Date.now();
        if (now - lastCpfPassportRun < CPF_PASSPORT_DEBOUNCE) return;
        lastCpfPassportRun = now;
        
        const countryField = jQuery('#billing_country');
        const countryFieldWrapper = jQuery('#billing_country_field');
        const cpfField = jQuery('#billing_cpf_field');
        let passportField = jQuery('#billing_passport_field');
        
        if (countryFieldWrapper.length === 0 || cpfField.length === 0) return;

        // Verifica se ordem já está correta (evita reaplicar desnecessariamente)
        const prevOfCountry = countryFieldWrapper.next();
        const prevId = prevOfCountry.attr('id') || '';
        const orderLooksOk = (prevId === 'billing_passport_field' || prevId === 'billing_cpf_field');
        // Se o AJAX removeu o campo de passaporte mas o país não é BR, NÃO pode dar return aqui — precisa recriar/reordenar.
        if (orderLooksOk && passportField.length > 0) {
            const selectedCountry = countryField.val();
            if (selectedCountry === 'BR') {
                cpfField.show().find('input').prop('required', true);
                passportField.hide().find('input').prop('required', false);
                sanitizePassportInput(passportField.find('input'));
            } else {
                cpfField.hide().find('input').prop('required', false).val('');
                passportField.show().find('input').prop('required', true);
                sanitizePassportInput(passportField.find('input'));
            }
            countryField.off('change.passport select2:select.passport').on('change.passport select2:select.passport', function() { handleCpfPassportFields(); });
            return;
        }

        // 1. Se campo Passport não existe no HTML (fragmento antigo), criar clonando o CPF
        if (passportField.length === 0) {
            const newPassportField = cpfField.clone();
            newPassportField.attr('id', 'billing_passport_field');
            newPassportField.addClass('form-row-wide').addClass('pxo-passport-field');
            newPassportField.find('label').attr('for', 'billing_passport').html('Passport Number <abbr class="required" title="required">*</abbr>');
            const $passInp = newPassportField.find('input');
            $passInp.attr({
                'id': 'billing_passport',
                'name': 'billing_passport',
                'placeholder': 'Passport Number'
            }).val('');
            sanitizePassportInput($passInp);
            newPassportField.hide();
            passportField = newPassportField;
        }
        
        // 2. Pausa observer para não entrar em loop
        if (cpfPassportObserver) { cpfPassportObserver.disconnect(); }
        
        // 3. Forçar ordem no DOM: Country → Passport → CPF
        countryFieldWrapper.after(passportField);
        passportField.after(cpfField);
        
        // 4. Reconecta observer após 400ms
        const wrapper = document.querySelector('.woocommerce-billing-fields__field-wrapper');
        setTimeout(function() {
            if (cpfPassportObserver && wrapper) {
                cpfPassportObserver.observe(wrapper, { childList: true, subtree: true });
            }
        }, 400);
        
        function toggleFields() {
            const selectedCountry = countryField.val();
            const cpfFieldRef = jQuery('#billing_cpf_field');
            const passportFieldRef = jQuery('#billing_passport_field');
            
            if (selectedCountry === 'BR') {
                cpfFieldRef.show().find('input').prop('required', true);
                passportFieldRef.hide().find('input').prop('required', false).val('');
                sanitizePassportInput(passportFieldRef.find('input'));
            } else {
                cpfFieldRef.hide().find('input').prop('required', false).val('');
                passportFieldRef.show().find('input').prop('required', true);
                sanitizePassportInput(passportFieldRef.find('input'));
            }
        }
        
        toggleFields();
        countryField.off('change.passport select2:select.passport').on('change.passport select2:select.passport', toggleFields);
    }
    
    function scheduleCpfPassportReorder() {
        const delays = [50, 150, 350, 700, 1200, 2000];
        delays.forEach(function(d) {
            setTimeout(handleCpfPassportFields, d);
        });
    }
    
    jQuery(document).ready(function() {
        scheduleCpfPassportReorder();
    });
    
    jQuery(document.body).on('updated_checkout checkout_error', function() {
        lastCpfPassportRun = 0;
        scheduleCpfPassportReorder();
    });

    // Antes do envio: garante CPF/passaporte coerentes com o país (evita CPF oculto voltar a validar errado)
    jQuery(document.body).on('checkout_place_order', function() {
        lastCpfPassportRun = 0;
        handleCpfPassportFields();
    });
    
    // MutationObserver: detecta quando o formulário é substituído (AJAX)
    const billingWrapper = document.querySelector('.woocommerce-billing-fields__field-wrapper');
    if (billingWrapper) {
        const cpfObserver = new MutationObserver(function() {
            scheduleCpfPassportReorder();
        });
        cpfObserver.observe(billingWrapper, { childList: true, subtree: true });
    }
    
    // Verificação periódica nos primeiros 8s (captura updates lentos)
    let checkCount = 0;
    const checkInterval = setInterval(function() {
        if (jQuery('#billing_country_field').length && jQuery('#billing_cpf_field').length) {
            const countryNext = jQuery('#billing_country_field').next();
            const countryNextId = countryNext.attr('id') || '';
            if (countryNextId !== 'billing_passport_field' && countryNextId !== 'billing_cpf_field') {
                handleCpfPassportFields();
            }
        }
        if (++checkCount >= 16) clearInterval(checkInterval);
    }, 500);
    
})();
