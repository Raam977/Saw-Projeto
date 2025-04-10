# 🔐 SAW – Segurança de Aplicações Web

Projeto académico no âmbito da unidade curricular de **Segurança de Aplicações Web**, focado na construção de uma aplicação Web segura para **Gestão de Salas**, com implementação em **PHP** e **MySQL**.

---

## 🎯 Objetivo

Desenvolver uma aplicação Web que permita gerir a utilização de salas, assegurando os princípios de segurança, privacidade e controlo de acessos.

---

## 🧩 Funcionalidades

### 🧾 Funcionalidades gerais:
- Registo de utilizadores
- Recuperação de conta ("Forgot me")
- "Remember me" com sessão persistente
- Autenticação de utilizadores

---

## 🌐 Áreas da Aplicação

### 1. Área Pública
Visível a todos os utilizadores, registados ou não.

- Listagem de todas as salas existentes
- Não apresenta o estado das salas

---

### 2. Área do Utilizador Registado (Utente)
Apenas acessível após login.

- **Perfil de utilizador**
  - Upload de imagem de perfil
  - Edição dos próprios dados
  - Restrição de acesso ao perfil de outros utentes

- **Gestão de Salas**
  - Consulta da disponibilidade das salas por dia e hora
  - Requisição de salas (reserva que torna a sala indisponível para os restantes)
  - Listagem das salas requisitadas pelo próprio

---

### 3. Área de Administração
Acesso restrito a utilizadores com permissões de administrador.

- **Gestão de Utilizadores**
  - Listagem de todos os utilizadores
  - Visualização das salas requisitadas por cada professor

- **Gestão de Salas**
  - Inserção, alteração e eliminação de salas
  - Atribuição de estado: Disponível, Indisponível, Brevemente

- **Ocupação de Salas**
  - Indicação e visualização da ocupação das salas por dia

---

## ⚙️ Tecnologias Utilizadas

- **Linguagem Backend:** PHP (sem frameworks)
- **Base de Dados:** MySQL
- **Frontend:** HTML5, CSS3, JavaScript (puro)
- **Segurança:** 
  - Proteção contra SQL Injection
  - Hashing de palavras-passe (bcrypt)
  - Validação e sanitização de inputs
  - MFA 2F (Google Authenticator)
  - Bloqueio de tentativas de bruteforce (ip publico e mac address)
  - Email de aviso de bruteforce de falhas para admins
  - Recuperação de password por e-mail com token que expira e só é valido pelo umavesz.
  - Necessário código para alterar palavra-passe que é enviado por e-mail.
---

---

## 🔒 Considerações de Segurança

- **Autenticação baseada em sessão**
- **Proteção contra CSRF e XSS**
- **Gestão de permissões por perfil**
- **Validação do lado cliente e servidor**

---

## 👥 Autores

- Ricardo Mendes  
- Diogo Melo

---

## 📜 Licença

Este projeto é apenas para fins académicos.

