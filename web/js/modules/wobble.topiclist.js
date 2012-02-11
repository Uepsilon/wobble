"use strict";
function TopicsListDisplay() {};
// Event Handlers -------------------------------------------------
TopicsListDisplay.prototype.onTopicClicked = function(topic) {};
TopicsListDisplay.prototype.onCreateNewTopic = function() {};
TopicsListDisplay.prototype.onShowArchived = function() {};
TopicsListDisplay.prototype.onShowInbox = function() {};
// Methods --------------------------------------------------------
TopicsListDisplay.prototype.showLoading = function() {};
TopicsListDisplay.prototype.setActiveTopic = function(topic) {};
TopicsListDisplay.prototype.renderTopicList = function(topics) {};
TopicsListDisplay.prototype.clear = function() {};



/**
 * The Business logic for the topics list-view
 */
function TopicListPresenter (view, cache) {
	this.view = view;
	this.cache = cache
	this.cacheTimeout = 60 * 60 * 24 * 5;

	this.selectedTopicId = null;
	this.topics = cache.get('topicslistpresenter.topics') || [];
	this.show_archived = cache.get('topicslistpresenter.show_archived') || 0;

	// Start fetching an up2date list
	this.refreshTopicsList();

	// Prerender the view from the cache
	this.view.clear();
	this.view.renderTopicList(this.topics);
	
	var that = this;
	// UI Callbacks
	this.view.onTopicClicked = function(topic) {
		that.setSelectedTopic(topic); 
	};
	this.view.onCreateNewTopic = function() {
		that.createNewTopic();
	};
	this.view.onShowArchived = $.proxy(function() {
		that.setShowArchived(1);
	}, this);
	this.view.onShowInbox = function() {
		that.setShowArchived(0);
	}
	
	// BUS Events
	BUS.on('topic.changed', function(_data) {
		this.refreshTopicsList();
	}, this);
	
	BUS.on('topic.post.changed', function(_data) {
		this.refreshTopicsList();
	}, this);
	BUS.on('api.notification', function(message) {
		if ( message.type == 'topic_changed' ||
			 message.type == 'post_changed' /* Unread message counter propably got changed */ ) {
			this.refreshTopicsList();
		}
	}, this);
	
};
/** Called by the view when a new topic should be created */
TopicListPresenter.prototype.refreshTopicsList = function() {
	API.list_topics(this.show_archived, $.proxy(function(err, list) {
		if (!err) {
			this.cache.set('topicslistpresenter.topics', list, this.cacheTimeout);

			this.view.clear();
			this.topics = list;
			this.view.renderTopicList(list);

			for (var i = 0; i < list.length; i++) {
				var data = list[i];
				if (this.selectedTopicId && this.selectedTopicId == data.id) {
					this.view.setActiveTopic(data);
				}
			}
		}
	}, this));
};

TopicListPresenter.prototype.setShowArchived = function setShowArchived(show_archived) {
	this.view.showLoading();
	this.show_archived = show_archived;
	this.cache.set('topicslistpresenter.show_archived', show_archived, this.cacheTimeout);

	this.selectedTopicId = null;
	this.refreshTopicsList();
}

TopicListPresenter.prototype.setSelectedTopic = function(topic, noEvent) {
	this.selectedTopicId = topic.id;
	this.view.setActiveTopic(topic);
	if (!noEvent) {
		BUS.fire('topic.selected', topic.id);
	}
};
TopicListPresenter.prototype.createNewTopic = function() {
	// TODO: Check if the user is currently editing something and submit that before going on
	var that = this;

	var topicId = API.generate_id();
	
	// Create a topic on the server and notify the TopicView (async)
	var that = this;
	API.topics_create(topicId, function(err, topic_id) {
		if (err) {
			that.refreshTopicsList();
		}
		if (topic_id !== undefined) {					
			BUS.fire('topic.topic.created', topicId);
		}
	});
	
	// Create a dummy TopicHeader, so we can render something immediately	
	var topicDetails = {
		id: topicId,
		abstract: '-',
		users: [API.user()],
		post_count_total: 1,
		post_count_unread: 0
	};

	this.topics.splice(0, 0, topicDetails); // Prepend the item to the ViewList
	this.view.clear();
	this.view.renderTopicList(this.topics);
	this.setSelectedTopic(topicDetails, true);
};
