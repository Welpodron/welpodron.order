"use strict";
(() => {
    if (window.welpodron && window.welpodron.templater) {
        if (window.welpodron.order) {
            return;
        }
        const MODULE_BASE = "order";
        const EVENT_LOAD_BEFORE = `welpodron.${MODULE_BASE}:load:before`;
        const EVENT_LOAD_AFTER = `welpodron.${MODULE_BASE}:load:after`;
        const ATTRIBUTE_BASE = `data-w-${MODULE_BASE}`;
        const ATTRIBUTE_BASE_ID = `${ATTRIBUTE_BASE}-id`;
        const ATTRIBUTE_CONTROL = `${ATTRIBUTE_BASE}-control`;
        const ATTRIBUTE_ACTION = `${ATTRIBUTE_BASE}-action`;
        const ATTRIBUTE_ACTION_ARGS = `${ATTRIBUTE_ACTION}-args`;
        const ATTRIBUTE_ACTION_ARGS_SENSITIVE = `${ATTRIBUTE_ACTION_ARGS}-sensitive`;
        const ATTRIBUTE_ACTION_FLUSH = `${ATTRIBUTE_ACTION}-flush`;
        class Order {
            sessid = "";
            element = null;
            supportedActions = ["load"];
            isLoading = false;
            constructor({ sessid, element, config = {} }) {
                if (Order.instance) {
                    Order.instance.sessid = sessid;
                    return Order.instance;
                }
                this.setSessid(sessid);
                this.setElement(element);
                document.removeEventListener("click", this.handleDocumentClick);
                document.addEventListener("click", this.handleDocumentClick);
                if (window.JCCatalogItem) {
                    window.JCCatalogItem.prototype.changeInfo =
                        this.handleOfferChange(window.JCCatalogItem.prototype.changeInfo);
                }
                if (window.JCCatalogElement) {
                    window.JCCatalogElement.prototype.changeInfo =
                        this.handleOfferChange(window.JCCatalogElement.prototype.changeInfo);
                }
                Order.instance = this;
            }
            handleDocumentClick = (event) => {
                let { target } = event;
                if (!target) {
                    return;
                }
                target = target.closest(`[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}][${ATTRIBUTE_ACTION_ARGS}]`);
                if (!target) {
                    return;
                }
                const action = target.getAttribute(ATTRIBUTE_ACTION);
                const actionArgs = target.getAttribute(ATTRIBUTE_ACTION_ARGS);
                const actionArgsSensitive = target.getAttribute(ATTRIBUTE_ACTION_ARGS_SENSITIVE);
                if (!actionArgs && !actionArgsSensitive) {
                    return;
                }
                const actionFlush = target.getAttribute(ATTRIBUTE_ACTION_FLUSH);
                if (!actionFlush) {
                    event.preventDefault();
                }
                if (!action || !this.supportedActions.includes(action)) {
                    return;
                }
                const actionFunc = this[action];
                if (actionFunc instanceof Function) {
                    return actionFunc({
                        args: actionArgs,
                        argsSensitive: actionArgsSensitive,
                        event,
                    });
                }
            };
            handleOfferChange = (func) => {
                return function () {
                    let beforeId = this.productType === 3
                        ? this.offerNum > -1
                            ? this.offers[this.offerNum].ID
                            : 0
                        : this.product.id;
                    let afterId = -1;
                    let index = -1;
                    let boolOneSearch = true;
                    for (let i = 0; i < this.offers.length; i++) {
                        boolOneSearch = true;
                        for (let j in this.selectedValues) {
                            if (this.selectedValues[j] !== this.offers[i].TREE[j]) {
                                boolOneSearch = false;
                                break;
                            }
                        }
                        if (boolOneSearch) {
                            index = i;
                            break;
                        }
                    }
                    if (index > -1) {
                        afterId = this.offers[index].ID;
                    }
                    else {
                        afterId = this.product.id;
                    }
                    if (beforeId && afterId && beforeId !== afterId) {
                        document
                            .querySelectorAll(`[${ATTRIBUTE_ACTION_ARGS}="${beforeId}"][${ATTRIBUTE_ACTION}="load"][${ATTRIBUTE_CONTROL}]`)
                            .forEach((element) => {
                            const actionArgs = element.getAttribute(ATTRIBUTE_ACTION_ARGS);
                            if (!actionArgs) {
                                return;
                            }
                            if (this.productType === 3) {
                                if (this.offers[index].CAN_BUY) {
                                    element.removeAttribute("disabled");
                                    element.style.display = "";
                                }
                                else {
                                    element.setAttribute("disabled", "");
                                    element.style.display = "none";
                                }
                            }
                            else {
                                if (this.product.canBuy) {
                                    element.removeAttribute("disabled");
                                    element.style.display = "";
                                }
                                else {
                                    element.setAttribute("disabled", "");
                                    element.style.display = "none";
                                }
                            }
                            element.setAttribute(ATTRIBUTE_ACTION_ARGS, afterId.toString());
                        });
                    }
                    func.call(this);
                };
            };
            setSessid = (sessid) => {
                this.sessid = sessid;
            };
            setElement = (element) => {
                this.element = element;
            };
            load = async ({ args, argsSensitive, event, }) => {
                if (this.isLoading) {
                    return;
                }
                this.isLoading = true;
                const controls = document.querySelectorAll(`[${ATTRIBUTE_ACTION_ARGS}="${args}"][${ATTRIBUTE_ACTION}][${ATTRIBUTE_CONTROL}]`);
                controls.forEach((control) => {
                    control.setAttribute("disabled", "");
                });
                const data = new FormData();
                const from = this.element?.getAttribute(ATTRIBUTE_BASE_ID);
                if (from) {
                    data.set("from", from);
                }
                data.set("sessid", this.sessid);
                data.set("args", args);
                data.set("argsSensitive", argsSensitive);
                let dispatchedEvent = new CustomEvent(EVENT_LOAD_BEFORE, {
                    bubbles: true,
                    cancelable: false,
                });
                document.dispatchEvent(dispatchedEvent);
                try {
                    const response = await fetch("/bitrix/services/main/ajax.php?action=welpodron%3Aorder.Receiver.load", {
                        method: "POST",
                        body: data,
                    });
                    if (!response.ok) {
                        throw new Error(response.statusText);
                    }
                    const bitrixResponse = await response.json();
                    if (bitrixResponse.status === "error") {
                        console.error(bitrixResponse);
                        const error = bitrixResponse.errors[0];
                        window.welpodron.templater.renderHTML({
                            string: error.message,
                            container: this.element,
                            config: {
                                replace: true,
                            },
                        });
                    }
                    else {
                        const { data: responseData } = bitrixResponse;
                        window.welpodron.templater.renderHTML({
                            string: responseData,
                            container: this.element,
                            config: {
                                replace: true,
                            },
                        });
                    }
                }
                catch (error) {
                    console.error(error);
                }
                finally {
                    this.isLoading = false;
                    controls.forEach((control) => {
                        control.removeAttribute("disabled");
                    });
                    dispatchedEvent = new CustomEvent(EVENT_LOAD_AFTER, {
                        bubbles: true,
                        cancelable: false,
                    });
                    document.dispatchEvent(dispatchedEvent);
                }
            };
        }
        window.welpodron.order = Order;
    }
})();
