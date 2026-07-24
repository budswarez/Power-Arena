# Tema Arena

Tema WordPress Arena - reconstrução limpa e moderna.

## Desenvolvimento

- PHP: classes em `inc/` sob o namespace `Arena\`, autoloadadas por convenção de nome de arquivo (`Arena\Foo\Bar` -> `inc/Foo/Bar.php`), ver `functions.php`.
- Testes: PHPUnit (`vendor/bin/phpunit`), rodando via `wp-env`:
  ```bash
  wp-env run tests-cli --env-cwd=wp-content/themes/arena vendor/bin/phpunit
  ```

## Preview em produção

O tema inclui um mu-plugin que permite pré-visualizar o Arena em produção, requisição a requisição, sem alterar o tema ativo para os demais visitantes.

**Localização no repositório:** o código-fonte do mu-plugin vive em
`themes/arena/mu-plugins/arena-preview.php` (versionado junto do tema). Um
mu-plugin real do WordPress precisa estar em `wp-content/mu-plugins/`, uma
pasta fora do escopo deste repositório — por isso o arquivo aqui é a fonte, e
o deploy o **copia** para o destino final.

A lógica de decisão pura e testada vive em `Arena\Preview::shouldPreview()`
(`inc/Preview.php`, cobertura em `tests/PreviewTest.php`). O mu-plugin não
pode usar o autoloader do tema (mu-plugins carregam antes dele), então ele
replica a mesma regra inline.

1. Definir em `wp-config.php`: `define('ARENA_PREVIEW_TOKEN', '<token-secreto>');`
2. Deploy: copiar `themes/arena/mu-plugins/arena-preview.php` para `wp-content/mu-plugins/arena-preview.php` na produção.
3. Acessar como admin logado (com a capability `edit_theme_options`), ou usar `?arena_preview=<token-secreto>`.
4. **Cache:** garantir que o preview rode logado (LiteSpeed/WP Rocket ignoram logados) ou
   excluir o parâmetro `arena_preview` do cache. Sem isso, páginas cacheadas mostram o tema errado.
