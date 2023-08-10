(() => {
  if ((window as any).welpodron && (window as any).welpodron.templater) {
    if ((window as any).welpodron.order) {
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

    type _BitrixResponse = {
      data: any;
      status: "success" | "error";
      errors: {
        code: string;
        message: string;
        customData: string;
      }[];
    };

    type OrderConfigType = {};

    type OrderPropsType = {
      sessid: string;
      element: HTMLElement;
      config?: OrderConfigType;
    };

    class Order {
      sessid = "";

      element: HTMLElement | null = null;

      supportedActions = ["load"];

      isLoading = false;

      constructor({ sessid, element, config = {} }: OrderPropsType) {
        if ((Order as any).instance) {
          (Order as any).instance.sessid = sessid;
          return (Order as any).instance;
        }

        this.setSessid(sessid);
        this.setElement(element);

        document.removeEventListener("click", this.handleDocumentClick);
        document.addEventListener("click", this.handleDocumentClick);

        if ((window as any).JCCatalogItem) {
          (window as any).JCCatalogItem.prototype.changeInfo =
            this.handleOfferChange(
              (window as any).JCCatalogItem.prototype.changeInfo
            );
        }

        if ((window as any).JCCatalogElement) {
          (window as any).JCCatalogElement.prototype.changeInfo =
            this.handleOfferChange(
              (window as any).JCCatalogElement.prototype.changeInfo
            );
        }

        (Order as any).instance = this;
      }

      handleDocumentClick = (event: MouseEvent) => {
        let { target } = event;

        if (!target) {
          return;
        }

        target = (target as Element).closest(
          `[${ATTRIBUTE_CONTROL}][${ATTRIBUTE_ACTION}][${ATTRIBUTE_ACTION_ARGS}]`
        );

        if (!target) {
          return;
        }

        const action = (target as Element).getAttribute(
          ATTRIBUTE_ACTION
        ) as keyof this;

        const actionArgs = (target as Element).getAttribute(
          ATTRIBUTE_ACTION_ARGS
        );

        const actionArgsSensitive = (target as Element).getAttribute(
          ATTRIBUTE_ACTION_ARGS_SENSITIVE
        );

        if (!actionArgs && !actionArgsSensitive) {
          return;
        }

        const actionFlush = (target as Element).getAttribute(
          ATTRIBUTE_ACTION_FLUSH
        );

        if (!actionFlush) {
          event.preventDefault();
        }

        if (!action || !this.supportedActions.includes(action as string)) {
          return;
        }

        const actionFunc = this[action] as any;

        if (actionFunc instanceof Function) {
          return actionFunc({
            args: actionArgs,
            argsSensitive: actionArgsSensitive,
            event,
          });
        }
      };

      handleOfferChange = (func: Function) => {
        return function () {
          let beforeId =
            this.productType === 3
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
          } else {
            afterId = this.product.id;
          }

          if (beforeId && afterId && beforeId !== afterId) {
            document
              .querySelectorAll(
                `[${ATTRIBUTE_ACTION_ARGS}="${beforeId}"][${ATTRIBUTE_ACTION}="load"][${ATTRIBUTE_CONTROL}]`
              )
              .forEach((element) => {
                const actionArgs = (element as Element).getAttribute(
                  ATTRIBUTE_ACTION_ARGS
                );

                if (!actionArgs) {
                  return;
                }

                if (this.productType === 3) {
                  if (this.offers[index].CAN_BUY) {
                    element.removeAttribute("disabled");
                    (element as HTMLElement).style.display = "";
                  } else {
                    element.setAttribute("disabled", "");
                    (element as HTMLElement).style.display = "none";
                  }
                } else {
                  if (this.product.canBuy) {
                    element.removeAttribute("disabled");
                    (element as HTMLElement).style.display = "";
                  } else {
                    element.setAttribute("disabled", "");
                    (element as HTMLElement).style.display = "none";
                  }
                }

                element.setAttribute(ATTRIBUTE_ACTION_ARGS, afterId.toString());
              });
          }

          func.call(this);
        };
      };

      setSessid = (sessid: string) => {
        this.sessid = sessid;
      };

      setElement = (element: HTMLElement) => {
        this.element = element;
      };

      load = async ({
        args,
        argsSensitive,
        event,
      }: {
        args?: unknown;
        argsSensitive?: unknown;
        event?: Event;
      }) => {
        if (this.isLoading) {
          return;
        }

        this.isLoading = true;

        const controls = document.querySelectorAll(
          `[${ATTRIBUTE_ACTION_ARGS}="${args}"][${ATTRIBUTE_ACTION}][${ATTRIBUTE_CONTROL}]`
        );

        controls.forEach((control) => {
          control.setAttribute("disabled", "");
        });

        const data = new FormData();

        const from = this.element?.getAttribute(ATTRIBUTE_BASE_ID);

        if (from) {
          data.set("from", from);
        }

        data.set("sessid", this.sessid);
        data.set("args", args as any);
        data.set("argsSensitive", argsSensitive as any);

        let dispatchedEvent = new CustomEvent(EVENT_LOAD_BEFORE, {
          bubbles: true,
          cancelable: false,
        });

        document.dispatchEvent(dispatchedEvent);

        try {
          const response = await fetch(
            "/bitrix/services/main/ajax.php?action=welpodron%3Aorder.Receiver.load",
            {
              method: "POST",
              body: data,
            }
          );

          if (!response.ok) {
            throw new Error(response.statusText);
          }

          const bitrixResponse: _BitrixResponse = await response.json();

          if (bitrixResponse.status === "error") {
            console.error(bitrixResponse);

            const error = bitrixResponse.errors[0];

            (window as any).welpodron.templater.renderHTML({
              string: error.message,
              container: this.element as HTMLElement,
              config: {
                replace: true,
              },
            });
          } else {
            const { data: responseData } = bitrixResponse;

            (window as any).welpodron.templater.renderHTML({
              string: responseData,
              container: this.element as HTMLElement,
              config: {
                replace: true,
              },
            });
          }
        } catch (error) {
          console.error(error);
        } finally {
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

    (window as any).welpodron.order = Order;
  }
})();
