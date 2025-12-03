/**
 * ============================================================
 * MÓDULO: Exclusão de Conta de Usuário
 * ============================================================
 * 
 * Propósito:
 * Gerencia o processo de exclusão permanente de conta do usuário.
 * Integra Firebase Authentication com backend PHP.
 * 
 * Funcionalidades:
 * - Confirmação dupla (confirm dialog)
 * - Validação de email verificado (required)
 * - Envio de token JWT para API PHP
 * - Exclusão no Firebase e MySQL
 * - Logout automático após exclusão
 * 
 * Requisitos de Segurança:
 * - Email deve estar verificado
 * - Token JWT deve ser válido
 * - Usuário deve confirmar ação
 * 
 * Fluxo:
 * 1. Usuário clica "Deletar Conta"
 * 2. Mostra alerta de confirmação
 * 3. Obtém token JWT do Firebase (refreshed)
 * 4. Envia para /api/auth/delete.php
 * 5. Backend valida e exclui dados
 * 6. Logout do Firebase
 * 7. Redireciona para home
 */

// ===== IMPORTAÇÕES DO FIREBASE =====

/**
 * SDK modular do Firebase v9.23.0.
 * Importações:
 * - initializeApp: inicializa app Firebase
 * - getAuth: obtém instância de autenticação
 * - sendEmailVerification: envia email de verificação
 * - signOut: faz logout do usuário
 */
import { initializeApp } from "https://www.gstatic.com/firebasejs/9.23.0/firebase-app.js";
import {
  getAuth,
  sendEmailVerification,
  signOut,
} from "https://www.gstatic.com/firebasejs/9.23.0/firebase-auth.js";

// ===== CONFIGURAÇÃO DO FIREBASE =====

/**
 * Credenciais do projeto Firebase (IMPERIUM-0001).
 * 
 * Nota de Segurança:
 * - apiKey é PÚBLICA (não é secreta)
 * - Segurança real vem de Firebase Security Rules
 * - Token JWT validado no backend PHP
 */
const firebaseConfig = {
  apiKey: "AIzaSyBtblDahBpfrT4CaLl2viS0D2890iJ_RFE",
  authDomain: "imperium-0001.firebaseapp.com",
  projectId: "imperium-0001",
  storageBucket: "imperium-0001.firebasestorage.app",
  messagingSenderId: "961834611988",
  appId: "1:961834611988:web:0a2ad6089630324094be01",
  measurementId: "G-M39V86RLKS",
};

// Inicializa aplicação Firebase
const app = initializeApp(firebaseConfig);
// Obtém instância do serviço de autenticação
const auth = getAuth(app);

// ===== SELEÇÃO DO BOTÃO DE EXCLUSÃO =====

/**
 * Botão "Deletar Conta" na página de perfil.
 * HTML esperado: <a href="#" id="btn-delete-account">DELETAR</a>
 */
const btnDelete = document.getElementById("btn-delete-account");

// ===== EVENT LISTENER: EXCLUSÃO DE CONTA =====

/**
 * Verifica se botão existe (pode não estar presente em todas as páginas).
 * Guard clause: previne erros se elemento não existir.
 */
if (btnDelete) {
  /**
   * Handler do clique no botão de exclusão.
   * Processo assíncrono (async/await) para comunicação com APIs.
   */
  btnDelete.addEventListener("click", async (e) => {
    // Previne comportamento padrão do link (navegação)
    e.preventDefault();

    // ===== ETAPA 1: CONFIRMAÇÃO DO USUÁRIO =====
    /**
     * Mostra diálogo de confirmação com avisos claros.
     * Retorna se usuário cancelar (não prossegue com exclusão).
     */
    if (
      !confirm(
        "ATENÇÃO: Tem certeza que deseja excluir sua conta permanentemente?\n\n- Todo o seu histórico de pedidos será apagado.\n- Seus favoritos e carrinho serão perdidos.\n- Esta ação NÃO pode ser desfeita."
      )
    ) {
      return;
    }

    // ===== ETAPA 2: VALIDAÇÃO DO USUÁRIO LOGADO =====
    /**
     * Obtém usuário atualmente autenticado no Firebase.
     * null se não houver sessão ativa.
     */
    const user = auth.currentUser;
    if (!user) {
      alert(
        "Erro: Não foi possível identificar o usuário logado. Tente recarregar a página."
      );
      return;
    }

    try {
      // ===== ETAPA 3: OBTENÇÃO DO TOKEN JWT =====
      /**
       * Força refresh do token para garantir claims atualizadas.
       * 
       * Parâmetro true: força renovação (não usa cache).
       * 
       * Token contém:
       * - uid: ID único do usuário
       * - email: email verificado
       * - email_verified: boolean
       * - exp: timestamp de expiração
       */
      const token = await user.getIdToken(true);

      // ===== ETAPA 4: REQUISIÇÃO À API DE EXCLUSÃO =====
      /**
       * POST para /api/auth/delete.php
       * 
       * Headers:
       * - Authorization: Bearer {token} - JWT do Firebase
       * - Content-Type: application/json
       * 
       * Backend PHP valida token e exclui:
       * - Registro na tabela Usuario (MySQL)
       * - Pedidos relacionados
       * - Carrinho e favoritos
       */
      const response = await fetch("../../api/auth/delete.php", {
        method: "POST",
        headers: {
          Authorization: "Bearer " + token,
          "Content-Type": "application/json",
        },
      });

      // ===== ETAPA 5: PROCESSAMENTO DA RESPOSTA =====
      /**
       * Lê resposta como texto primeiro (mais seguro).
       * Tenta fazer parse como JSON.
       * Isso previne erro se backend retornar HTML de erro.
       */
      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (e) {
        console.error("Resposta não-JSON:", text);
        throw new Error("O servidor retornou uma resposta inválida.");
      }

      // ===== ETAPA 6: TRATAMENTO DE SUCESSO =====
      if (response.ok && data.success) {
        alert("Sua conta foi excluída com sucesso.");
        // Faz logout do Firebase (limpa sessão client-side)
        await signOut(auth);
        // Redireciona para página inicial
        window.location.href = "../../../index.php";
      } else {
        // ===== ETAPA 7: TRATAMENTO DE ERROS ESPECÍFICOS =====
        
        /**
         * Erro: EMAIL_NOT_VERIFIED
         * Backend rejeita exclusão se email não verificado.
         * Oferece reenvio de verificação.
         */
        if (data.code === "EMAIL_NOT_VERIFIED") {
          if (
            confirm(
              data.message +
                "\n\nDeseja receber um novo email de verificação agora?"
            )
          ) {
            // Envia novo email de verificação
            await sendEmailVerification(user);
            alert(
              "Email enviado! Verifique sua caixa de entrada (e spam) e tente novamente após verificar."
            );
          }
        } else {
          // Outros erros: exibe mensagem do backend
          alert(
            "Erro ao excluir conta: " + (data.message || "Erro desconhecido")
          );
        }
      }
    } catch (error) {
      // ===== ETAPA 8: TRATAMENTO DE EXCEÇÕES =====
      /**
       * Captura erros de rede, timeout, parsing JSON, etc.
       * Log para debugging + alerta amigável para usuário.
       */
      console.error("Erro na exclusão:", error);
      alert("Ocorreu um erro ao processar sua solicitação: " + error.message);
    }
  });
}
