$(function () {
    var phoneMaskBehavior = function (val) {
        return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 00000-0000';
    };

    $('#telefone, #tel, input[name="telefone"]').mask(phoneMaskBehavior, {
        onKeyPress: function (val, e, field, options) {
            field.mask(phoneMaskBehavior.apply({}, arguments), options);
        }
    });

    $('#cep, #input-cep').mask('00000-000');
    $('#cpf, #CPF, input[name="cpf"]').mask('000.000.000-00');
});