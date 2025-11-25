const formatBR=n=>n.toFixed(2).replace('.',',');
const parseNumber=v=>Number(String(v).replace(',', '.'))||0;

let freteSelecionado=0;

function carregarCarrinho(){
  const lista=document.getElementById('lista-carrinho');
  const carrinho=JSON.parse(localStorage.getItem('carrinho'))||[];
  lista.innerHTML='';

  if(carrinho.length===0){
    lista.innerHTML='<p style="color:#aaa">Seu carrinho está vazio.</p>';
  }

  carrinho.forEach((item,index)=>{
    const qtd=item.qtd||item.quantidade||1;
    const div=document.createElement('div');
    div.className='item';
    div.innerHTML=`
      <img src="../${item.imagem}">
      <div class="meta">
        <h3>${item.nome}</h3>
        <p>R$ ${formatBR(parseNumber(item.preco))}</p>
        <div class="tamanho">Tamanho: ${item.tamanho?item.tamanho:'-'}</div>
      </div>

      <div class="qtd-control">
        <button onclick="mudarQtd(${index}, -1)">-</button>
        <div id="qtd-${index}" style="min-width:22px;text-align:center">${qtd}</div>
        <button onclick="mudarQtd(${index}, 1)">+</button>
      </div>

      <button class="remover" onclick="remover(${index})">Remover</button>
    `;
    lista.appendChild(div);
  });

  atualizarTotais();
  verificarBotaoFinalizar();
}

function mudarQtd(index,delta){
  const carrinho=JSON.parse(localStorage.getItem('carrinho'))||[];
  if(!carrinho[index])return;
  carrinho[index].qtd=(carrinho[index].qtd||carrinho[index].quantidade||1)+delta;
  if(carrinho[index].qtd<1)carrinho[index].qtd=1;
  localStorage.setItem('carrinho',JSON.stringify(carrinho));
  carregarCarrinho();
}

function remover(index){
  const carrinho=JSON.parse(localStorage.getItem('carrinho'))||[];
  carrinho.splice(index,1);
  localStorage.setItem('carrinho',JSON.stringify(carrinho));
  carregarCarrinho();
}

function atualizarTotais(){
  const carrinho=JSON.parse(localStorage.getItem('carrinho'))||[];
  let subtotal=0;
  carrinho.forEach(i=>{
    const price=parseNumber(i.preco||i.unit_price||0);
    const qtd=i.qtd||i.quantidade||1;
    subtotal+=price*qtd;
  });

  document.getElementById('subtotal').textContent=formatBR(subtotal);
  document.getElementById('frete-valor').textContent=formatBR(freteSelecionado);

  const total=subtotal+freteSelecionado;
  document.getElementById('total').textContent=formatBR(total);

  const pixTotalEl=document.getElementById('pix-total');
  if(pixTotalEl)pixTotalEl.textContent=formatBR(total);
}

async function consultarFrete(){
  const cep=document.getElementById('input-cep').value.replace(/\D/g,'');
  const resultado=document.getElementById('resultado-frete');
  const mapa=document.getElementById('mapa-frete');

  resultado.innerHTML='';
  mapa.innerHTML='';

  if(cep.length!==8){
    resultado.innerHTML='<p>CEP inválido. Digite 8 números.</p>';
    freteSelecionado=0;
    atualizarTotais();
    verificarBotaoFinalizar();
    return;
  }

  resultado.innerHTML='<p style="color:#aaa">Consultando...</p>';
  try{
    const res=await fetch(`https://viacep.com.br/ws/${cep}/json/`);
    const data=await res.json();
    if(data.erro){
      resultado.innerHTML='<p>CEP não encontrado.</p>';
      freteSelecionado=0;
      atualizarTotais();
      verificarBotaoFinalizar();
      return;
    }

    const logradouro=data.logradouro||'Rua não informada';
    const cidade=data.localidade||'';
    const uf=data.uf||'';

    let valorFrete=0;
    let prazo='';
    if(uf==='SP'){
      if(cidade.toLowerCase()==='são paulo'){
        valorFrete=15;
        prazo='1 a 2 dias úteis';
      }else{
        valorFrete=25;
        prazo='2 a 4 dias úteis';
      }
    }else if(['RJ','MG','PR'].includes(uf)){
      valorFrete=35;
      prazo='3 a 6 dias úteis';
    }else{
      valorFrete=50;
      prazo='5 a 9 dias úteis';
    }

    freteSelecionado=valorFrete;
    resultado.innerHTML=`
      <p><strong>${logradouro}</strong></p>
      <p>${cidade} - ${uf}</p>
      <p>Frete: <strong>R$ ${formatBR(valorFrete)}</strong></p>
      <p>Prazo: <strong>${prazo}</strong></p>
    `;

    mapa.innerHTML=`
      <iframe width="100%" height="200" frameborder="0" style="border:0"
        src="https://www.google.com/maps?q=${encodeURIComponent(logradouro+', '+cidade+' - '+uf)}&output=embed"></iframe>
    `;
    atualizarTotais();
    verificarBotaoFinalizar();
  }catch(err){
    console.error(err);
    resultado.innerHTML='<p>Erro ao consultar o CEP.</p>';
    freteSelecionado=0;
    atualizarTotais();
    verificarBotaoFinalizar();
  }
}

function verificarBotaoFinalizar(){
  const carrinho=JSON.parse(localStorage.getItem('carrinho'))||[];
  const temProduto=carrinho.length>0;
  const freteOk=freteSelecionado>0;
  const numero=document.getElementById('input-numero').value.trim().length>0;
  const btn=document.getElementById('btn-finalizar');

  const habilitar=temProduto&&freteOk&&numero;
  btn.disabled=!habilitar;
}

function gerarPixCode(){
  let s='';
  for(let i=0;i<44;i++){s+=String(Math.floor(Math.random()*10));}
  return s;
}

document.getElementById('btn-finalizar').addEventListener('click',()=>{
  const telaCarrinho=document.getElementById('tela-carrinho');
  const telaPix=document.getElementById('tela-pix');

  const totalText=document.getElementById('total').textContent;
  document.getElementById('pix-total').textContent=totalText;

  const pixCode=gerarPixCode();
  document.getElementById('copia-cola').value=pixCode;

  telaCarrinho.style.display='none';
  telaPix.style.display='block';

  document.getElementById('msg-sucesso').textContent='';
});

document.getElementById('btn-japaguei').addEventListener('click',()=>{
  document.getElementById('msg-sucesso').textContent='Compra realizada com sucesso!';
  localStorage.removeItem('carrinho');

  setTimeout(()=>{
    window.location.href='PRODUTOS.html';
  },4000);
});

document.getElementById('btn-cep').addEventListener('click',consultarFrete);
document.getElementById('input-cep').addEventListener('keypress',e=>{if(e.key==='Enter')consultarFrete();});
document.getElementById('input-numero').addEventListener('input',verificarBotaoFinalizar);

carregarCarrinho();
