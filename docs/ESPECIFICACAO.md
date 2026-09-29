# Relógio de Oração — Especificação do Projeto

## 1. Visão geral

Desenvolver uma aplicação web simples para organizar o **Relógio de Oração** da **Igreja Evangélica Assembleia de Deus Madureira — Interestadual de Patrocínio/MG (IEADIP)**.

O administrador cadastra uma edição do relógio, informando a data, o horário de início e o horário de término. Os participantes acessam um link compartilhável, consultam os blocos de uma hora e informam o nome para assumir um horário. Mais de uma pessoa pode assumir o mesmo bloco.

A aplicação deve informar se cada horário está **disponível** ou **já tem participante(s)**, sem revelar ao público os nomes de quem escolheu cada horário.

## 2. Objetivo deste documento

Este documento descreve o comportamento esperado do produto, não impõe uma implementação específica. O agente de desenvolvimento (Claude) deverá escolher a tecnologia mais adequada, considerando simplicidade de instalação, manutenção e uso.

- **Backend:** considerar Laravel como uma opção, mas decidir se ele é realmente necessário para este escopo.
- **Banco de dados:** considerar SQLite como padrão para uma instalação simples; justificar qualquer alternativa.
- **Frontend:** fica a critério do agente de desenvolvimento, priorizando clareza, acessibilidade e boa experiência em celulares.
- Evitar dependências e infraestrutura desnecessárias para uma aplicação de pequeno porte.

## 3. Usuários e permissões

### 3.1 Administrador

- Acessa a área administrativa pelo caminho `/admin`.
- Não haverá fluxo de login na primeira versão, conforme o requisito atual.
- Pode cadastrar e consultar edições do relógio de oração.
- Pode visualizar os nomes e horários escolhidos para fins de organização.
- Pode copiar/obter o link público de cada edição para compartilhar.

**Observação de segurança:** uma área administrativa sem autenticação pode ser acessada e alterada por qualquer pessoa que descubra o endereço. A implementação deve deixar esse risco documentado. O agente deve avaliar uma proteção simples, como senha de ambiente, autenticação básica ou um código de acesso, e explicar a recomendação antes de adotar qualquer solução que altere o requisito de “sem login”. Não expor dados pessoais dos participantes nas páginas públicas.

### 3.2 Participante

- Acessa o link público enviado pela igreja.
- Consulta os horários do evento e seu estado de disponibilidade.
- Informa o nome e escolhe um horário.
- Não precisa criar conta.
- Não vê os nomes de outras pessoas nem a quantidade exata de participantes de cada horário.

## 4. Funcionalidades

### 4.1 Administração de edições

A página `/admin` deve permitir:

1. Cadastrar uma edição do relógio de oração com:
   - Data;
   - Horário de início;
   - Horário de término.
2. Consultar as edições cadastradas.
3. Acessar os participantes e horários de uma edição pela área administrativa.
4. Copiar ou abrir o link público específico de uma edição.

As edições costumam ocorrer às sextas-feiras, mas a aplicação **não deve impedir outras datas**. A data é escolhida pelo administrador.

Cada edição deve ter um identificador/link público próprio, para que o administrador possa compartilhar o evento correto. O link não deve depender de a pessoa conhecer ou digitar o endereço administrativo.

### 4.2 Geração dos horários

- Cada horário de oração tem duração fixa de **1 hora**.
- A aplicação deve gerar blocos consecutivos entre o início e o término informados.
- Exemplo: início às 07:00 e término às 10:00 gera:
  - 07:00–08:00
  - 08:00–09:00
  - 09:00–10:00
- O horário de término representa o limite final do último bloco; não há bloco começando no horário de término.
- Para manter todos os blocos com uma hora, a duração total deve ser um múltiplo de 60 minutos. Se não for, a aplicação deve impedir o cadastro e explicar a regra (a menos que o agente proponha e documente outra regra antes da implementação).
- O intervalo deve ser válido: horário final posterior ao inicial. Na primeira versão, não permitir que um evento atravesse a meia-noite; isso evita ambiguidades com a data e com a duração dos blocos.

### 4.3 Inscrição pública

Na página pública de uma edição, apresentar:

- Nome/identificação do Relógio de Oração;
- Data e horários do evento, em formato local brasileiro;
- Todos os blocos de uma hora;
- Estado de cada bloco:
  - **Disponível** — ninguém se inscreveu;
  - **Já tem participante(s)** — pelo menos uma inscrição existe.
- Um formulário para a pessoa informar o nome e escolher um bloco.

Ao enviar o formulário:

- Validar o nome e o horário selecionado;
- Registrar a inscrição vinculada à edição e ao bloco;
- Confirmar o sucesso sem revelar nomes de outras pessoas;
- Atualizar o estado do bloco para “Já tem participante(s)”.

Mais de uma pessoa pode se inscrever no mesmo bloco. A página pública não deve bloquear uma inscrição apenas porque outra pessoa já escolheu aquele horário.

### 4.4 Privacidade da página pública

- Não mostrar os nomes dos participantes ao público.
- Não mostrar listas de participantes, telefones, e-mails ou outros dados pessoais.
- Não mostrar necessariamente a quantidade de pessoas inscritas; basta indicar se o horário está ocupado ou disponível.
- A resposta da aplicação (HTML, APIs, dados embutidos na página, metadados) também não pode expor os nomes ou outros dados que a interface apenas esconde visualmente.
- Os nomes são visíveis somente na área de administração, sujeita à ressalva de segurança da seção 3.1.

## 5. Regras de validação e comportamento

1. Data, início e fim são obrigatórios.
2. Início deve ser anterior ao fim.
3. O período deve resultar em blocos completos de uma hora.
4. Não aceitar inscrição sem nome ou sem um bloco válido.
5. Não aceitar inscrição para uma edição inexistente ou inválida.
6. O horário exibido e gravado deve seguir o fuso `America/Sao_Paulo`.
7. A aplicação deve tratar envios repetidos sem criar duplicações acidentais causadas por duplo clique ou recarregamento. O agente deve implementar uma estratégia adequada e, quando possível, oferecer uma confirmação clara após o envio.
8. Como o público pode enviar inscrições simultaneamente, a gravação deve ser segura contra concorrência e não pode sobrescrever inscrições existentes.
9. Exibir mensagens de erro e sucesso em português, com linguagem simples.
10. A interface deve funcionar em dispositivos móveis e ser acessível por teclado, com rótulos claros e contraste legível.

## 6. Modelo de dados sugerido

A estrutura final fica a critério do agente, mas deve representar pelo menos:

### Edição / evento

- Identificador;
- Token ou identificador público não sequencial para o link compartilhável;
- Data;
- Horário de início;
- Horário de término;
- Datas de criação e atualização, se forem úteis.

### Inscrição

- Identificador;
- Referência à edição;
- Início do bloco escolhido (ou referência a uma entidade de bloco);
- Nome informado pelo participante;
- Data de criação.

Os blocos podem ser calculados a partir do início e do término, sem precisar armazenar uma linha por bloco. Caso sejam armazenados, garantir que as inscrições estejam vinculadas ao bloco e que a capacidade seja ilimitada.

Guardar somente os dados necessários. Não solicitar telefone, e-mail ou outros dados pessoais na primeira versão.

## 7. Páginas e navegação esperadas

### Administração — `/admin`

- Formulário para cadastrar evento;
- Lista de eventos existentes;
- Link/ação para abrir a gestão de cada evento;
- Link público de compartilhamento;
- Visão administrativa de participantes por horário.

### Página pública — rota por edição

- Informações do evento;
- Relação de todos os horários;
- Indicador de disponibilidade/ocupação;
- Formulário de inscrição (nome + horário);
- Confirmação depois do envio.

O caminho público exato fica a critério do agente, desde que cada edição tenha um link único, compartilhável e difícil de adivinhar. A experiência deve ser simples para pessoas que acessam o link pelo WhatsApp.

## 8. Fora do escopo da primeira versão

A menos que seja necessário para uma implementação segura ou que o solicitante aprove depois, não incluir:

- Cadastro/login de participantes;
- Pagamentos;
- Notificações automáticas por WhatsApp ou e-mail;
- Aprovação manual de inscrições;
- Limite de pessoas por horário;
- Aplicativo nativo para celular;
- Recorrência automática semanal;
- Cancelamento/edição de inscrições pelo próprio participante;
- Envio de dados pessoais além do nome.

## 9. Critérios de aceite

A entrega será considerada funcional quando:

1. O administrador conseguir cadastrar um evento com data, início e fim válidos.
2. A aplicação gerar todos os blocos consecutivos de uma hora corretamente.
3. Horários inválidos ou períodos que não formam horas completas forem rejeitados com mensagem clara.
4. O administrador conseguir obter um link público específico para o evento.
5. Uma pessoa conseguir abrir esse link, ver todos os horários e distinguir os disponíveis dos que já têm inscrição.
6. Uma pessoa conseguir informar o nome e se inscrever em um horário disponível.
7. Outra pessoa conseguir se inscrever no mesmo horário sem que a primeira inscrição seja substituída ou que a segunda seja bloqueada.
8. A página pública nunca revelar nomes ou outros dados de participantes, inclusive por meio dos dados recebidos pelo navegador.
9. O administrador conseguir ver os nomes e horários inscritos na área administrativa.
10. As páginas e mensagens estiverem em português e forem utilizáveis em celular.
11. A escolha de framework, armazenamento e dependências estiver documentada, com justificativa simples e instruções para executar o projeto localmente.

## 10. Orientação ao agente de desenvolvimento (Claude)

Implemente o produto descrito neste documento. Antes de codificar, decida e explique brevemente:

1. Se Laravel é necessário ou se uma solução mais simples atende melhor ao escopo;
2. Se SQLite é adequado para a instalação prevista;
3. Qual tecnologia de frontend será usada.

Não trate Laravel como obrigatório: o solicitante deixou essa decisão para você. Mantenha os requisitos de produto, especialmente a duração fixa de uma hora, múltiplas pessoas por horário, link público por evento e privacidade dos nomes na página pública.

Depois, implemente a aplicação completa, inclua instruções de instalação e execução, e verifique os fluxos principais com testes ou uma lista de validação reproduzível. Informe explicitamente a limitação de segurança causada pela área `/admin` sem autenticação e recomende uma proteção apropriada para publicação na internet.
