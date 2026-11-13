# Kashiwazaki SEO ProfilePage Maker

![Version](https://img.shields.io/badge/version-1.0.1-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)
![License](https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg)

schema.org **ProfilePage / Person** 構造化データを完備した、SEO・E-E-A-T 強化型のプロフィールページ生成プラグインです。人物の知的資産（著作・特許・論文・取材・登壇など）を機械可読化し、Google Knowledge Graph と AI 検索（AI Overviews / Perplexity 等）の双方から認知される Authority Page を作成します。

## 主な機能

- **専用プロフィール投稿タイプ**: 管理画面から氏名・肩書・経歴・実績などを 1 ページに集約して作成・管理できます。
- **構造化データ (JSON-LD) 自動出力**: ProfilePage + Person を中核に、学術論文 (ScholarlyArticle)・記事 (Article)・書籍 (Book)・特許・取材・講演・受賞・資格などを schema.org 型でマークアップします。パンくず (BreadcrumbList) も出力します。
- **URL プレフィックスのカスタマイズ**: `/profile/` や `/member/` など、プロフィールページの URL 階層を自由に設定できます（複数プレフィックス対応）。
- **柔軟な表示順プリセット**: 標準レイアウトと「page7 風」レイアウトを切り替えできます。
- **カスタムブロック**: ツール紹介などの汎用的な繰り返しコンテンツを追加できます。
- **ショートコード対応**: プロフィール本文や特定セクションをページ内に埋め込めます。動的な日付ショートコードなどとも併用可能です。
- **画像・権限の安全性**: 添付画像の検証や投稿ごとの権限チェックなど、セキュリティに配慮した実装です。

## 動作環境

- WordPress 6.0 以上
- PHP 8.0 以上

## インストール

1. 本プラグインのフォルダ `wp-plugin-kashiwazaki-seo-profilepage-maker` を `wp-content/plugins/` にアップロードします。
2. WordPress 管理画面の「プラグイン」から **Kashiwazaki SEO ProfilePage Maker** を有効化します。
3. 管理メニューに追加される **Kashiwazaki SEO ProfilePage Maker** から、最初のプロフィールを作成します。

## 使い方

1. 管理メニュー **Kashiwazaki SEO ProfilePage Maker → 新規プロフィール** で氏名・経歴・実績などを入力します。
2. **設定** タブで URL プレフィックスや表示順プリセットを調整します。
3. 公開後、生成された URL（例: `https://example.com/profile/your-slug/`）でプロフィールページを確認します。
4. 必要に応じて Google リッチリザルトテストで構造化データの出力を検証します。

詳細な操作手順は `docs/` 内の HTML マニュアルを参照してください。

## ライセンス

本プラグインは [GPL-2.0-or-later](LICENSE) の下で配布されています。

## 作者

柏崎剛 (Tsuyoshi Kashiwazaki) — <https://www.tsuyoshikashiwazaki.jp/profile/>
