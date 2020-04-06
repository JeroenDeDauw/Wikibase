import MwWindow from '@/@types/mediawiki/MwWindow';
import DataBridgeConfig from '@/@types/wikibase/DataBridgeConfig';
import AppBridge from '@/definitions/AppBridge';
import prepareContainer from '@/mediawiki/prepareContainer';
import { SelectedElement } from '@/mediawiki/SelectedElement';
import subscribeToEvents from '@/mediawiki/subscribeToEvents';
import Tracker from '@/tracking/Tracker';

export default class Dispatcher {
	public static readonly APP_DOM_CONTAINER_ID = 'data-bridge-container';

	private readonly mwWindow: MwWindow;
	private readonly app: AppBridge;
	private readonly dataBridgeConfig: DataBridgeConfig;
	private readonly eventTracker: Tracker;

	public constructor(
		mwWindow: MwWindow,
		app: AppBridge,
		dataBridgeConfig: DataBridgeConfig,
		eventTracker: Tracker,
	) {
		this.mwWindow = mwWindow;
		this.app = app;
		this.dataBridgeConfig = dataBridgeConfig;
		this.eventTracker = eventTracker;
	}

	public dispatch( selectedElement: SelectedElement ): void {
		const dialog = prepareContainer( this.mwWindow.OO, this.mwWindow.$, Dispatcher.APP_DOM_CONTAINER_ID );

		const emitter = this.app.launch(
			{
				containerSelector: `#${Dispatcher.APP_DOM_CONTAINER_ID}`,
			},
			{
				pageTitle: this.mwWindow.mw.config.get( 'wgPageName' ),
				entityId: selectedElement.entityId,
				propertyId: selectedElement.propertyId,
				entityTitle: selectedElement.entityTitle,
				editFlow: selectedElement.editFlow,
				client: {
					usePublish: this.dataBridgeConfig.usePublish,
					issueReportingLink: this.dataBridgeConfig.issueReportingLink,
				},
				originalHref: selectedElement.link.href,
				pageUrl: this.mwWindow.location.href,
			},
			this.app.createServices(
				this.mwWindow,
				this.dataBridgeConfig.editTags,
				this.eventTracker,
			), // should be made caching when used repeatedly
		);

		subscribeToEvents( emitter, dialog.getManager(), this.mwWindow );
	}
}
