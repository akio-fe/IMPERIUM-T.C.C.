document.addEventListener('DOMContentLoaded', () => {
      const input = document.querySelector('.search-bar input');
      const fechar = document.querySelector('.search-bar .fechar');
      const lupa = document.querySelector('.icons .pesquisar');
      const icon = document.querySelector('.search-bar .search-icon');

      const botoesFiltro = document.querySelectorAll('.filtros button');
      const navFiltroLinks = document.querySelectorAll('header nav a[data-tipo]');
      const produtos = document.querySelectorAll('.produto');

      const filtrosValidos = ['todos', 'calcados', 'calcas', 'blusas', 'camisas', 'conjuntos', 'outros'];
      const params = new URLSearchParams(window.location.search);
      const filtroURL = params.get('filtro');
      const filtroInicial = filtrosValidos.includes(filtroURL) ? filtroURL : null;
      const filtroNavAtivo = document.querySelector('header nav a[data-tipo].active');
      let tipoAtual = filtroInicial || filtroNavAtivo?.dataset.tipo || 'todos';

      if (lupa && input && fechar && icon) {
        lupa.addEventListener('click', () => {
          input.classList.add('mostrar');
          fechar.style.display = 'inline-block';
          lupa.style.display = 'none';
          icon.style.display = 'block';
          input.focus();
        });

        fechar.addEventListener('click', () => {
          input.classList.remove('mostrar');
          fechar.style.display = 'none';
          lupa.style.display = 'inline-block';
          icon.style.display = 'none';
          input.value = '';
          aplicarFiltro('todos');
        });

        input.addEventListener('input', () => aplicarFiltro());
      }

      function aplicarFiltro(tipo = tipoAtual) {
        tipoAtual = tipo;
        atualizarClassesAtivas(tipoAtual);

        const termoBusca = (input?.value || '').trim().toLowerCase();
        produtos.forEach(produto => {
          const tipoProduto = produto.getAttribute('data-tipo') || 'outros';
          const titulo = produto.querySelector('h3').textContent.toLowerCase();
          const passaFiltroTipo = (tipoAtual === 'todos') || (tipoProduto === tipoAtual);
          const passaBusca = termoBusca === '' || titulo.includes(termoBusca);
          produto.style.display = (passaFiltroTipo && passaBusca) ? 'block' : 'none';
        });

        const url = new URL(window.location);
        if (tipoAtual === 'todos') {
          url.searchParams.delete('filtro');
        } else {
          url.searchParams.set('filtro', tipoAtual);
        }
        window.history.replaceState({}, '', url);
      }

      function atualizarClassesAtivas(tipo) {
        botoesFiltro.forEach(botao => {
          const ehMesmoTipo = botao.dataset.tipo === tipo;
          botao.classList.toggle('ativo', ehMesmoTipo);
        });
        navFiltroLinks.forEach(link => {
          const ehMesmoTipo = link.dataset.tipo === tipo;
          link.classList.toggle('active', ehMesmoTipo);
        });
      }

      botoesFiltro.forEach(botao => {
        botao.addEventListener('click', () => aplicarFiltro(botao.dataset.tipo));
      });

      navFiltroLinks.forEach(link => {
        link.addEventListener('click', event => {
          event.preventDefault();
          aplicarFiltro(link.dataset.tipo);
          link.blur();
        });
      });

      aplicarFiltro(tipoAtual);
    });