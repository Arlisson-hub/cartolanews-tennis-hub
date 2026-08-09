# Guia de Imagens

## Tamanhos registrados

| Nome | Resolução | Proporção | Uso |
|---|---|---|---|
| `cn-tennis-hero` | 1920×640 | 3:1 | Hero da central |
| `cn-tennis-player-card` | 800×1000 | 4:5 | Perfil do jogador, destaques, brasileiros |
| `cn-tennis-legend-card` | 800×1000 | 4:5 | Cards de lendas |
| `cn-tennis-avatar` | 320×320 | 1:1 | Rankings, listas, power ranking |
| `cn-tennis-tournament-card` | 1200×675 | 16:9 | Cards de torneio |
| `cn-tennis-surface-card` | 800×500 | 8:5 | Especial por superfície |
| Open Graph | 1200×630 | — | Compartilhamento em redes sociais (gerado pelo SEO do tema/plugin de SEO ativo) |

Registrados via `add_image_size()` em `CN_Tennis_Images::register_sizes()`, chamado em `after_setup_theme`. O WordPress gera automaticamente cada tamanho a partir da imagem original enviada — não é preciso preparar recortes manualmente.

## Posição focal

Cada jogador/lenda tem os campos `photo_focal_x` e `photo_focal_y` (0–100%), editáveis na tela de edição. Eles alimentam as variáveis CSS `--cn-focal-x`/`--cn-focal-y`, consumidas por:

```css
.cn-tennis-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: var(--cn-focal-x, 50%) var(--cn-focal-y, 50%);
}
```

Isso permite reposicionar o rosto do atleta dentro do card (ex.: X: 65%, Y: 30%) sem editar o arquivo de imagem.

## Nunca distorce

Nenhum lugar do plugin usa `width: 100%; height: 100%;` sem `object-fit`. Todos os cards usam `aspect-ratio` fixo + `object-fit: cover`, então a imagem sempre preenche o espaço sem esticar, tanto no desktop quanto no celular.

## Responsive images

`CN_Tennis_Images::render()` sempre passa pelo `wp_get_attachment_image()` nativo do WordPress, que já gera `srcset`/`sizes` automaticamente a partir dos tamanhos registrados — a imagem de 1920px do hero nunca é carregada dentro de um avatar de 320px.

- **Lazy loading:** ativado por padrão (`loading="lazy"`) em todas as imagens abaixo da dobra.
- **LCP do hero:** carregado sem lazy loading (evita atraso na maior imagem visível da página).
- **WebP/AVIF:** gerado automaticamente pelo WordPress quando o servidor suporta (não é responsabilidade deste plugin, é comportamento nativo do core moderno).

## Placeholder (quando não há foto)

Nunca aparece uma imagem quebrada. Sem foto cadastrada, o plugin mostra um placeholder elegante gerado 100% em CSS: as iniciais do jogador sobre um gradiente azul CartolaNews, com uma silhueta genérica de fundo (SVG embutido no próprio CSS, sem depender de nenhum arquivo — ver `.cn-tennis-placeholder` em `public/css/cn-tennis-public.css`).

## Créditos de imagem (Wikimedia Commons)

Ao importar uma foto do Wikimedia Commons pelo admin (`CN_Tennis_Images::import_from_wikimedia()`), o plugin grava automaticamente:

- autor (`photo_credit_author`);
- licença (`photo_credit_license`) — só licenças `CC BY`/`CC0`/domínio público são aceitas;
- URL da licença (`photo_credit_license_url`);
- URL da página de origem (`photo_credit_source_url`);
- data de importação (`photo_imported_at`).

Esses créditos aparecem automaticamente abaixo da foto em perfis de jogador e cards de lenda.
