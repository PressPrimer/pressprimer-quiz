const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	entry: {
		'question-editor': path.resolve(process.cwd(), 'src', 'question-editor', 'index.js'),
	},
	output: {
		path: path.resolve(process.cwd(), 'build'),
		filename: '[name].js',
	},
};
