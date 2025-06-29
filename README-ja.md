# PHPAssemble-Bare

PHPAssemble-Bareは、複数のPHPファイルを単一のファイルまたはPharにバンドルするツールです。

## 特徴
shebangとエントリーポイントを付けられるので、バンドルファイルを単一実行形式のように扱えます。
### 単一のファイル形式
設定ファイルの source_files に指定した順にファイルを連結するだけです(実行に必要なファイルをすべて指定する必要があります)。 但し、nikic/php-parser を使用してASTを解析し require / require_once や include / include_once は除去、グローバルスコープの return 文は goto 文に変換します。

### Phar形式
vendor/* と、設定ファイルの source_files に指定した順にファイルをPharに追加します。単純に追加するだけです。gzip/bzip2圧縮も可能です。

## インストール

```bash
git clone <repository-url>
cd PHPAssemble-Bare
composer install
```

## 使用方法

### 基本的な使用方法

```bash
# Pharアーカイブを作成（デフォルト）
php -d phar.readonly=0 bin/assemble --config=assemble-phar.json

# 単一ファイルバンドルを作成
php bin/assemble --config=assemble.json

# ヘルプ表示
php bin/assemble --help
```

### 設定ファイル (assemble.json)

```json
{
  "output": "dist/app.phar",
  "output_format": "phar",
  "entrypoint": "\\MyApp\\Application::main",
  "entrypoint_args": "$argc, $argv",
  "bundle_title": "マイアプリケーション",
  "keep_namespaces": true,
  "strict_types": true,
  "shebang_line": "#!/usr/bin/env php",
  "source_files": [
    "src/*.php"
  ],
  "source_files_exclude": [
    "src/test.php"
  ]
}
```

#### 設定オプション

| オプション | 型 | デフォルト | 説明 |
|-----------|-----|-----------|------|
| `output` | string | `bundle.php` | 出力ファイルのパス |
| `output_format` | string | `phar` | 出力形式: `bundle`, `phar`, `phar-gz`, `phar-bz2` |
| `entrypoint` | string | `""` (空文字) | 実行時に呼び出す関数/メソッド（オプション） |
| `entrypoint_args` | string | `$argc, $argv` | エントリーポイントに渡す引数 |
| `bundle_title` | string | `Bundle Version` | バンドルのタイトル（ヘッダーコメント用） |
| `keep_namespaces` | boolean | `true` | 名前空間宣言を保持するか |
| `strict_types` | boolean | `true` | `declare(strict_types=1)`を含めるか |
| `shebang_line` | string | `""` | 実行可能スクリプト用のシェバン行 |
| `source_files` | array | `[]` | バンドル対象のファイルパターンの配列 |
| `source_files_exclude` | array | `[]` | バンドルから除外するファイルパターンの配列 |

#### 出力形式

- **`bundle`**: 指定したソースコードを連結した単一PHPファイル
- **`phar`**: vendor/* と指定したソースコードを追加したPharアーカイブ（デフォルト形式）
- **`phar-gz`**: PharアーカイブのGZIP圧縮版
- **`phar-bz2`**: PharアーカイブのBZIP2圧縮版

#### エントリーポイントの動作

- **エントリーポイント有り**: 指定した関数を呼び出す実行可能スクリプトを作成
- **エントリーポイント無し**（空文字）: 他のスクリプトから読み込める形のライブラリを作成

### ワイルドカードパターン

- `*` - `/` 以外の任意の文字にマッチ
- `**` - `/` を含む任意の文字にマッチ（概念上）
- `src/*.php` - src/ ディレクトリ内の全ての .php ファイル
- `src/*/*.php` - src/ のサブディレクトリ内の .php ファイル

### ファイル除外機能

`source_files_exclude` を使用して、特定のファイルをバンドルから除外できます。

```json
{
  "source_files": [
    "src/*.php",
    "vendor/library/**/*.php"
  ],
  "source_files_exclude": [
    "src/Test.php",
    "src/Debug.php",
    "vendor/library/debug/*.php"
  ]
}
```

- 除外パターンも `source_files` と同様にワイルドカードをサポートします
- 除外処理は `source_files` の展開後に適用されます
- 完全一致で除外されるため、パスが正確に一致する必要があります

## 開発

### テスト

```bash
composer test
```

### ビルド

```bash
composer run build
```

## ライセンス

このプロジェクトのライセンス情報については、LICENSEファイルを参照してください。

## 依存関係

- **PHP**: 7.4以上
- **nikic/php-parser**: ^5.0

## 貢献

バグ報告や機能追加の提案は、GitHubのIssuesからお願いします。