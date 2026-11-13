# Changelog

このプロジェクトのすべての重要な変更はこのファイルに記録されます。

書式は [Keep a Changelog](https://keepachangelog.com/ja/1.0.0/) に準拠し、
バージョニングは [セマンティック バージョニング](https://semver.org/lang/ja/) に従います。

## [1.0.1] - 2026-11-13

### Added
- 「趣味・特技」に `[表示名](https://example.com/)` の形で書くと、その部分がリンクとして表示されるようにしました（リンク先は http / https のみ）。入力欄の下に書き方の説明を追加しました。

### Changed
- フロントの「現職・所属」「以前の経歴」の表で、「在任開始」「在任終了」の 2 列を「在任期間」の 1 列にまとめました（例: `2012-06-26 ～ 現在`、`2022-03-01 ～ 2026-05-31`）。現職で在任終了が空の行は「在任開始 ～ 現在」と表示します。構造化データの出力は変わりません。

## [1.0.0] - 2026-06-02

### Added
- 初回リリース。
- 専用プロフィール投稿タイプによる作成・管理機能。
- JSON-LD 構造化データの自動出力（ProfilePage + Person を中核に、学術論文・記事・書籍・特許・取材・講演・受賞・資格、およびパンくず BreadcrumbList）。
- URL プレフィックス（/profile/、/member/ 等、複数プレフィックス対応）のカスタマイズ。
- 標準レイアウトと page7 風レイアウトの表示順プリセット切替。
- ツール紹介などの汎用カスタムブロック。
- プロフィール本文・特定セクションを埋め込むショートコード。
- 添付画像の検証・投稿ごとの権限チェックなどのセキュリティ対策。

[1.0.1]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-profilepage-maker/releases/tag/v1.0.1
[1.0.0]: https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-profilepage-maker/releases/tag/v1.0.0
