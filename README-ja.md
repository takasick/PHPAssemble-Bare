# PHPAssemble-Bare

PHPAssemble-Bareは、複数のPHPファイルを単一のファイルにバンドルするツールです。

## 特徴

設定ファイルの source_files に指定した順にファイルを連結するだけです(実行に必要なファイルをすべて指定する必要があります)。
但し、nikic/php-parser を使用してASTを解析し require / require_once や include / include_once は除去、グローバルスコープの `return` 文は `goto` 文に変換します。

## インストール

```bash
git clone <repository-url>
cd PHPAssemble-Bare
composer install
```

## 使用方法

### 基本的な使用方法

```bash
# 直接実行
php bin/assemble.php --config=assemble.json

# ヘルプ表示
php bin/assemble.php --help
```

### 設定ファイル (assemble.json)

```json
{
  "output": "dist/bundle.php",
  "entrypoint": "\\PHPAssembleBare\\PHPAssembleBare::main",
  "entrypoint_args": "$argc, $argv",
  "bundle_title": "My Bundle",
  "keep_namespaces": true,
  "strict_types": true,
  "shebang_line": "#!/usr/bin/env php",
  "source_files": [
    "src/*.php",
    "vendor/some-library/src/**/*.php"
  ],
  "source_files_exclude": [
    "src/test.php",
    "vendor/some-library/src/debug*.php"
  ]
}
```

#### 設定オプション

| オプション | 型 | デフォルト | 説明 |
|-----------|-----|-----------|------|
| `output` | string | `bundle.php` | 出力ファイルのパス |
| `entrypoint` | string | `""` (空文字) | バンドルファイルの実行時に呼び出す関数/メソッド（オプション） |
| `entrypoint_args` | string | `$argc, $argv` | エントリーポイントに渡す引数 |
| `bundle_title` | string | `Bundle Version` | バンドルのタイトル（ヘッダーコメント用） |
| `keep_namespaces` | boolean | `true` | 名前空間宣言を保持するか |
| `strict_types` | boolean | `true` | `declare(strict_types=1)`を含めるか |
| `shebang_line` | string | `""` | 実行可能スクリプト用のシェバン行 |
| `source_files` | array | `[]` | バンドル対象のファイルパターンの配列 |
| `source_files_exclude` | array | `[]` | バンドルから除外するファイルパターンの配列 |

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