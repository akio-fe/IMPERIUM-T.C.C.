/**
 * ============================================================
 * MÓDULO: Máscaras de Formulário (jQuery Mask Plugin)
 * ============================================================
 * 
 * Propósito:
 * Aplica máscaras de formatação em campos de formulário.
 * Facilita entrada de dados padronizados (CPF, CEP, telefone).
 * 
 * Funcionalidades:
 * - Máscara de telefone (adaptativa: fixo/celular)
 * - Máscara de CEP (00000-000)
 * - Máscara de CPF (000.000.000-00)
 * - Aplicação automática ao carregar página
 * 
 * Dependências:
 * - jQuery 3.0+
 * - jQuery Mask Plugin 1.14.11+
 * 
 * Uso:
 * Basta incluir este script após jQuery e jQuery Mask.
 * Máscaras são aplicadas automaticamente em campos específicos.
 * 
 * Seletores suportados:
 * - Telefone: #telefone, #tel, input[name="telefone"]
 * - CEP: #cep, #input-cep
 * - CPF: #cpf, #CPF, input[name="cpf"]
 */

// ===== INICIALIZAÇÃO: DOCUMENT READY =====

/**
 * $(function() {...}): shorthand para $(document).ready()
 * Executa quando DOM estiver carregado (jQuery style).
 */
$(function () {
    // ===== MÁSCARA: TELEFONE (ADAPTATIVA) =====
    
    /**
     * Função que determina máscara baseada no comprimento.
     * 
     * Lógica:
     * - 10 dígitos (fixo): (00) 0000-0000
     * - 11 dígitos (celular): (00) 00000-0000
     * 
     * Nota: Atualmente retorna sempre (00) 00000-0000.
     * Para corrigir, usar:
     * return val.replace(/\D/g, '').length === 11 
     *   ? '(00) 00000-0000' 
     *   : '(00) 0000-0000';
     * 
     * @param {string} val - Valor atual do campo
     * @returns {string} - Máscara a aplicar
     */
    var phoneMaskBehavior = function (val) {
        return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 00000-0000';
    };

    /**
     * Aplica máscara em campos de telefone.
     * 
     * Seletores:
     * - #telefone: ID específico
     * - #tel: ID alternativo
     * - input[name="telefone"]: Por atributo name
     * 
     * Opções:
     * - onKeyPress: callback executado a cada tecla
     * - Reaplica máscara dinamicamente (adapta fixo/celular)
     */
    $('#telefone, #tel, input[name="telefone"]').mask(phoneMaskBehavior, {
        onKeyPress: function (val, e, field, options) {
            field.mask(phoneMaskBehavior.apply({}, arguments), options);
        }
    });

    // ===== MÁSCARA: CEP =====
    
    /**
     * Aplica máscara fixa de CEP brasileiro.
     * 
     * Padrão: 00000-000
     * Exemplo: 01310-100
     * 
     * Seletores:
     * - #cep: ID principal
     * - #input-cep: ID alternativo
     */
    $('#cep, #input-cep').mask('00000-000');
    
    // ===== MÁSCARA: CPF =====
    
    /**
     * Aplica máscara fixa de CPF brasileiro.
     * 
     * Padrão: 000.000.000-00
     * Exemplo: 123.456.789-00
     * 
     * Seletores:
     * - #cpf: ID minúsculo
     * - #CPF: ID maiúsculo
     * - input[name="cpf"]: Por atributo name
     */
    $('#cpf, #CPF, input[name="cpf"]').mask('000.000.000-00');
});