# Checklist de Homologação Fiscal — SEFAZ

Guia passo a passo para homologar a emissão de documentos fiscais **no seu servidor**, com segurança. O certificado digital **nunca** sai do seu ambiente.

---

## ⚠️ Antes de começar

- [ ] Você tem o **certificado digital A1** (.pfx ou .p12) da empresa
- [ ] Você sabe a **senha** do certificado
- [ ] A empresa está **credenciada na SEFAZ** do seu estado (ambiente de homologação)
- [ ] A **Inscrição Estadual** está ativa
- [ ] O sistema está rodando no seu servidor (não em ambiente local de teste)

> **NUNCA** envie o certificado ou a senha por e-mail, chat, ou para terceiros.
> O upload é feito **apenas** pela tela do próprio sistema, direto no seu servidor.

---

## Passo 1 — Configurar a empresa

No painel, complete o cadastro da empresa com **todos** os dados fiscais:

- [ ] CNPJ
- [ ] Inscrição Estadual (IE)
- [ ] Regime tributário (Simples Nacional, Lucro Presumido, etc.)
- [ ] Endereço completo com **código do município (IBGE)**
- [ ] UF
- [ ] Série e numeração inicial da NF-e

**Importante:** deixe o ambiente em **HOMOLOGAÇÃO** (`ambiente_nfe = 2`).

---

## Passo 2 — Enviar o certificado digital

Pela tela **Configurações → Certificado Digital**:

1. Selecione o arquivo .pfx
2. Digite a senha
3. Clique em "Validar" — o sistema mostra a validade e o CNPJ do certificado
4. Confirme o upload

O sistema:
- Criptografa o .pfx antes de armazenar (nunca em texto plano)
- Criptografa a senha no banco
- Confere se o CNPJ do certificado bate com o da empresa

---

## Passo 3 — Rodar o teste de homologação

No servidor, execute:

```bash
php artisan fiscal:testar-homologacao {ID_DA_EMPRESA}
```

O comando verifica 5 pontos:
1. ✓ Ambiente em homologação (segurança)
2. ✓ Configuração fiscal completa
3. ✓ Certificado válido e dentro da validade
4. ✓ Status do serviço SEFAZ (web service no ar)
5. ✓ Numeração configurada

Só prossiga quando passar **5/5**.

---

## Passo 4 — Emitir uma NF-e de teste

Em homologação, a SEFAZ **exige** que o nome do destinatário seja exatamente:

```
NF-E EMITIDA EM AMBIENTE DE HOMOLOGACAO - SEM VALOR FISCAL
```

1. Crie uma NF-e de teste com esse destinatário
2. Emita pela API ou painel
3. Verifique o retorno:
   - **cStat 100** = autorizada ✓
   - **cStat 110/301/302** = denegada (verifique a IE/situação cadastral)
   - Outros códigos = rejeitada (leia o motivo e corrija)

---

## Passo 5 — Validar o XML retornado

- [ ] O XML autorizado foi salvo no S3/MinIO
- [ ] O DANFE (PDF) foi gerado
- [ ] A chave de acesso tem 44 dígitos
- [ ] O protocolo de autorização foi registrado

---

## Passo 6 — Repetir para CT-e e NFS-e (se aplicável)

- **CT-e:** mesmo fluxo, mas valide também o RNTRC e o CIOT (se usar autônomos)
- **NFS-e:** depende do padrão do seu município (ABRASF, Paulistana, etc.)

---

## Passo 7 — Migrar para produção

**Só depois de validar tudo em homologação:**

1. Altere `ambiente_nfe = 1` (produção)
2. Solicite o credenciamento de **produção** na SEFAZ (diferente do de homologação)
3. Zere a numeração se necessário
4. Emita uma primeira nota real de baixo valor para confirmar

> ⚠️ O CI/CD do projeto **bloqueia** deploy se detectar ambiente de homologação
> misturado com produção. Confira a variável `SEFAZ_AMBIENTE` no `.env` de produção.

---

## Códigos de retorno SEFAZ mais comuns

| cStat | Significado | Ação |
|-------|-------------|------|
| 100 | Autorizada | Sucesso ✓ |
| 103 | Lote recebido | Aguardar processamento |
| 104 | Lote processado | Consultar resultado |
| 110 | Uso denegado | Verificar situação cadastral |
| 204 | Duplicidade de NF-e | Número já usado |
| 217 | NF-e não consta | Chave inexistente |
| 539 | Duplicidade de chave | Regerar com novo número |

---

## Suporte

Se um código de rejeição não estiver claro, consulte o **Manual de Orientação do Contribuinte (MOC)** da NF-e, seção de códigos de status.
