## Visão geral do projeto `nfse_escola`

- **Objetivo**: sistema independente de emissão de NFS-e (Padrão Nacional) para uma escola Maple Bear (unidade Botucatu), rodando em PHP num servidor local (futuro XAMPP na máquina do financeiro).
- **Status**: em uso e em evolução – já gera, assina e **emite NFS-e em homologação** diretamente no Portal Nacional, inspirado na implementação existente do Laboratório Bacchi, mas **sem alterar nada** do sistema original. Toda customização está isolada em `nfse_escola`.

### Arquitetura atual

- **Tecnologia**: PHP procedural, MySQL, HTML/CSS próprio (layout inspirado no site da Maple Bear).
- **Banco de dados**:
  - Script de criação: `nfse_escola/schema.sql`.
  - Banco: `nfse_maplebear`.
  - Tabelas:
    - `empresa`: dados fixos da escola (prestador da NFS-e, certificado A1, e-mail/SMTP, códigos de serviço).
    - `clientes`: tomadores (responsáveis financeiros e, opcionalmente, alunos).
    - `notas`: NFS-e geradas e emitidas pelo sistema (suporta **múltiplas notas por cliente e competência**, com valores/descrições diferentes).
    - `pdf_pendentes`: controle de PDFs não baixados (ainda não usado neste projeto).
- **Conexão com o banco**:
  - Arquivo: `nfse_escola/config/db.php`.
  - Atualmente aponta para:
    - Host: `192.168.0.4`
    - Banco: `nfse_maplebear`
    - Usuário: `lab`
    - Senha: `lab`

### Telas já criadas

- **`index.php`** – Painel inicial:
  - Layout Maple Bear (topo vermelho, logo “MB”, hero com texto “Always learn. Always organized.”).
  - Dashboard refinado com direção estética **“editorial escolar refinado”**, usando:
    - tipografia combinando fonte display para títulos e fonte de corpo para textos de apoio;
    - paleta consolidada em variáveis CSS (`--mb-red-600`, `--mb-gold`, neutros, estados de sucesso/alerta/erro);
    - motion sutil em cards e linhas da tabela para dar sensação de painel vivo sem exageros.
  - Seção **“Visão geral de faturamento”** reestruturada como um painel editorial da competência:
    - bloco à esquerda destacando a competência atual, um texto orientando o financeiro e um indicador de **% de notas do mês já emitidas**;
    - bloco à direita com métricas de apoio (clientes ativos, notas pendentes, emitidas no mês) usando os mesmos dados já calculados em PHP.
  - Tabela **“Fila de NFS-e”**:
    - mantém a listagem das últimas notas com foco em aluno, responsável, CPF/CNPJ, valor, competência e status;
    - ganhou hierarquia visual mais clara (largura reduzida para seleção, CPF/CNPJ com ênfase menor, cores de status coesas com o restante do sistema);
    - estados de hover nas linhas para facilitar a leitura quando a fila estiver cheia.

- **`empresa.php`** – Cadastro dos dados da escola:
  - Usa a tabela `empresa`.
  - Permite inserir/editar:
    - Razão social, nome fantasia, CNPJ, inscrição municipal.
    - Endereço completo, telefone, e-mail financeiro.
    - Código IBGE do município (ex.: Botucatu-SP = `3507506`).
    - Item da lista de serviços, códigos de tributação nacional/municipal, NBS, alíquota ISS.
    - Descrição padrão do serviço.
    - Caminho e senha do certificado A1 (`.pfx`).
    - Dados de SMTP (host, porta, usuário, senha, remetente).
  - Tem um botão **“Preencher com exemplo da NFS”** que importa dados de referência baseados na DANFSe `NF_MB.pdf` (Maple Bear Botucatu).

- **`clientes.php`** – Cadastro de clientes (tomadores):
  - Usa a tabela `clientes`.
  - Campos:
    - `nome_tomador` (responsável financeiro) – obrigatório.
    - `nome_aluno` – opcional.
    - `cpf_cnpj` – obrigatório (11 ou 14 dígitos).
    - `email` – obrigatório.
    - Endereço completo: logradouro, número, complemento, bairro, cidade, UF, CEP (8 dígitos).
    - Telefone – opcional.
    - `id_interno` / RA do aluno – opcional.
  - Validações já funcionando (e-mail, CPF/CNPJ, CEP, endereço).
  - Após corrigir o `bind_param`, o INSERT está assim (13 campos parametrizados + `ativo = 1`):
    - String de tipos: `'sssssssssssss'`.
    - Ordem: `nome_tomador, nome_aluno, cpf_cnpj, email, logradouro, numero, complemento, bairro, cidade, uf, cep, telefone, id_interno`.
  - Lista os 20 clientes ativos mais recentes em uma tabela.

### Navegação / Menu

- Topo comum em todas as páginas (`index.php`, `empresa.php`, `clientes.php`):
  - Logo `MB` e texto “Maple Bear · Financeiro NFSe”.
  - Menu em cápsula com abas:
    - **Notas fiscais** → `index.php`
    - **Escola** → `empresa.php`
    - **Clientes** → `clientes.php`
  - Indicador amarelo (`.topnav-indicator`) atrás da aba ativa:
    - Hoje a animação de transição entre páginas é **limitada**, porque cada aba carrega um PHP diferente (page reload completo). O código tenta suavizar, mas o comportamento perfeito de “deslizar entre abas” só será possível se um dia migrarmos o topo para uma SPA ou para troca de conteúdo via AJAX.

### Integração NFS-e (implementada e testada em homologação)

- **Classes copiadas/adaptadas** do projeto original para dentro de `nfse_escola/classes`:
  - `DPSBuilder.php`: monta o XML DPS conforme o XSD oficial (`DPS_v1.00.xsd`), usando:
    - dados do prestador em `empresa` (incluindo Simples Nacional e regime de apuração `regApTribSN`),
    - dados do tomador em `clientes`,
    - dados da nota em `notas`.
  - `XMLSigner.php`: assina o XML DPS com o certificado A1 da escola (`.pfx` em `nfse_escola/config`), no padrão XMLDSIG exigido pelo Portal.
  - `GZipHelper.php`: compacta/descompacta XML em GZip+Base64 para envio/recebimento.
  - `HTTPClient.php`: envia o DPS assinado para o endpoint `/nfse` do Portal Nacional via mTLS, usando os endpoints de **homologação** configurados em `config_nfse_escola.php`.
- **Adequações às novas regras do Simples Nacional** (a partir dos XSD/NT):
  - Uso de `opSimpNac = 3` para prestador ME/EPP optante do Simples.
  - Inclusão de `regApTribSN` (TSRegimeApuracaoSimpNac) quando optante do Simples, com valor padrão `1` (todos os tributos pelo SN), configurável em `config_nfse_escola.php`.
  - Ajuste em `tribMun` para **não informar alíquota (`pAliq`)** quando o prestador é ME/EPP optante do Simples, com apuração do ISS pelo SN e sem retenção (`tpRetISSQN = 1`), eliminando o erro **E0625**.
  - Normalização de `cTribNac` (código de tributação nacional) para conter apenas dígitos (`080101` em vez de `08.01.01`), evitando falha de pattern (`RNG6110`).
- **Fluxo efetivo de emissão em homologação**:
  - `gerar_notas.php`: gera notas `PENDENTE` para os clientes (sem mais bloquear duplicadas por cliente+competência).
  - `simular_xml.php`: gera e assina apenas o XML DPS para notas pendentes, salvando em `nfse_escola/data/xml/` (sem enviar ao governo).
  - `emitir_notas_homologacao.php`: seleciona notas pendentes, monta/assina o DPS, compacta, envia para o Portal Nacional (homologação) e:
    - em caso de sucesso (HTTP 201), descompacta a NFS-e, extrai número e chave, e atualiza a nota para `EMITIDA` com `numero_nfse`, `codigo_verificacao` (quando disponível), `chave_acesso` e `data_emissao`;
    - em caso de erro (HTTP 400, etc.), marca a nota como `ERRO`, grava `mensagem_erro` com o detalhe retornado e exibe um resumo legível na tela.
- **Resultado já validado**:
  - Pelo menos uma NFS-e de teste (ex.: nota `#3`) foi emitida com sucesso no **ambiente de homologação**, com:
    - número de NFS-e retornado (`nNFSe = 1` no teste),
    - chave de acesso de 50 dígitos,
    - sem erros de schema ou de regras do Simples (corrigidos E0160, E0166 e E0625).

### Certificados e NFS de referência

- Arquivo `NF_MB.pdf` na raiz de `TEMPLATE` é a NFS-e válida usada como referência:
  - Prestador: `MB BOTUCATU INSTITUICAO DE ENSINO LTDA`.
  - CNPJ: `31.653.761/0001-76`.
  - Endereço: DOUTOR COSTA LEITE, 2479, VILA NOGUEIRA, Botucatu/SP – CEP 18606-820.
  - Código de Tributação Nacional: `08.01.01 - Ensino regular pré-escolar, fundamental e médio.`
  - NBS: `122012000`.
  - Tributos aproximados: Federais 13,45%, Municipais 2,06% (usamos 2,06 como alíquota municipal de referência).

### Próximos passos óbvios (para retomar o trabalho depois)

1. **Ajustar/confirmar dados fiscais com a contabilidade/prefeitura**:
   - regime de apuração do Simples (`regApTribSN` = 1, 2 ou 3),
   - códigos de serviço (cTribNac, cTribMun, NBS) e alíquota do ISS.
2. **Polir a experiência do financeiro**:
   - destacar melhor, nas telas, o que é “simulação interna”, “emissão em homologação” e, futuramente, “produção”;
   - eventualmente adicionar filtros/edição/cancelamento de notas `PENDENTE`/`ERRO`.
3. **Preparar mudança de ambiente para produção**:
   - ajustar apenas `endpoint_producao` e `ambiente = 'producao'` em `config_nfse_escola.php`,
   - validar com 1–2 notas reais em horário combinado com o financeiro/contabilidade.
4. Opcional futuro: criar um front-end React/Tailwind com shadcn/ui e usar a skill `shadcn` + MCP para uma interface ainda mais moderna sobre o mesmo banco `nfse_maplebear`.

> **Resumo rápido:** `nfse_escola` é um mini-sistema de NFSe para a Maple Bear Botucatu, com banco próprio (`nfse_maplebear`), telas de escola/clientes/notas prontas, design inspirado na Maple Bear, e já capaz de **gerar, assinar e emitir NFS-e em homologação** no Portal Nacional, usando cópias adaptadas das classes do projeto do Laboratório Bacchi, sem impactar o sistema original.

