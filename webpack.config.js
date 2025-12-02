const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'question-editor': path.resolve(process.cwd(), 'src', 'question-editor', 'index.js'),
		'quiz-editor': path.resolve(process.cwd(), 'src', 'quiz-editor', 'index.js'),
		'bank-editor': path.resolve(process.cwd(), 'src', 'bank-editor', 'index.js'),
		'settings-panel': path.resolve(process.cwd(), 'src', 'settings-panel', 'index.js'),
		'dashboard': path.resolve(process.cwd(), 'src', 'dashboard', 'index.js'),
		'reports': path.resolve(process.cwd(), 'src', 'reports', 'index.js'),
		'blocks/quiz/index': path.resolve(process.cwd(), 'blocks', 'quiz', 'index.js'),
		'blocks/my-attempts/index': path.resolve(process.cwd(), 'blocks', 'my-attempts', 'index.js'),
	},
	output: {
		path: path.resolve(process.cwd(), 'build'),
		filename: (pathData) => {
			// Output blocks to their own directories
			if (pathData.chunk.name.startsWith('blocks/')) {
				return '[name].js';
			}
			return '[name].js';
		},
	},
};
