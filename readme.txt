=== Kashiwazaki SEO ProfilePage Maker ===
Contributors: tsuyoshikashiwazaki
Tags: seo, schema, structured-data, profile, json-ld
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

schema.org ProfilePage / Person 構造化データを完備した SEO・E-E-A-T 強化型プロフィールページ生成プラグイン。

== Description ==

schema.org ProfilePage / Person 構造化データを完備した、SEO・E-E-A-T 強化型のプロフィールページ生成プラグインです。人物の知的資産（著作・特許・論文・取材・登壇など）を機械可読化し、Google Knowledge Graph と AI 検索（AI Overviews / Perplexity 等）の双方から認知される Authority Page を作成します。

主な機能:

* 専用プロフィール投稿タイプによる作成・管理
* JSON-LD（ProfilePage + Person、学術論文・記事・書籍・特許・取材・講演・受賞・資格、パンくず）の自動出力
* URL プレフィックス（/profile/ や /member/ 等、複数対応）のカスタマイズ
* 標準 / page7 風の表示順プリセット切替
* ツール紹介などの汎用カスタムブロック
* ショートコードによる本文・セクション埋め込み
* 添付画像の検証・投稿ごとの権限チェックなどのセキュリティ配慮

== Installation ==

1. プラグインフォルダを wp-content/plugins/ にアップロードします。
2. 管理画面「プラグイン」から本プラグインを有効化します。
3. 管理メニュー「Kashiwazaki SEO ProfilePage Maker」から最初のプロフィールを作成します。

== Frequently Asked Questions ==

= 動作に必要な環境は? =

WordPress 6.0 以上、PHP 8.0 以上です。

= 構造化データが正しく出力されているか確認したい =

公開したプロフィール URL を Google リッチリザルトテストで検証してください。ProfilePage / Person を中心に各種 schema.org 型が出力されます。

= URL の階層 (プレフィックス) は変更できますか? =

設定タブから /profile/ や /member/ などを自由に設定でき、複数プレフィックスにも対応しています。

== Changelog ==

= 1.0.0 =
* 初回リリース。

== Upgrade Notice ==

= 1.0.0 =
初回リリースです。
