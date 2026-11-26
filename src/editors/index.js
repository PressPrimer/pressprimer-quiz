/**
 * Portable editors entry (build/editors.js, handle ppq-editors)
 *
 * All three editors in one bundle, with one Ant Design copy and no
 * code-splitting, so one set of translations covers every string. Hosts load
 * it with PPQ.editors.load() (the ppq-editors-loader handle) and render an
 * editor with PPQ.editors.mount(). wp-admin keeps its three editor entries.
 *
 * @package PressPrimer_Quiz
 * @since 3.2.0
 */

import { ConfigProvider, message } from 'antd';
import { getSlotRegistry } from './slots';
import { mount } from './mount';
import { getPpqTheme } from '../utils/ppqTheme';

// The editors' shared styles. The query makes them separate modules from the
// wp-admin entries' imports, so this bundle builds its own stylesheets
// instead of webpack moving them into a stylesheet the entries share.
/* eslint-disable import/no-unresolved -- the resolver doesn't read queries. */
import '../quiz-editor/style.css?editors';
import '../question-editor/styles/editor.css?editors';
import '../bank-editor/style.css?editors';
/* eslint-enable import/no-unresolved */
import './dashboard.css';

// Shares the slot registry with addon scripts already on the page.
getSlotRegistry();

// Toasts and confirm dialogs (Ant Design's static methods) take the shell's
// tokens too.
ConfigProvider.config({
	holderRender: (children) => <ConfigProvider theme={getPpqTheme()}>{children}</ConfigProvider>,
});

message.config({
	top: 50, // Below the WordPress admin bar (32px) plus some padding.
	duration: 5,
	maxCount: 3,
});

const editors = window.PPQ.editors;
editors.version = 1;
editors.mount = mount;

// For hosts that enqueue ppq-editors themselves instead of calling load().
document.dispatchEvent(new CustomEvent('ppq-editors-ready', { detail: { version: 1 } }));
