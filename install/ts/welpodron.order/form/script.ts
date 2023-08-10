(() => {
  if ((window as any).welpodron && (window as any).welpodron.templater) {
    if ((window as any).welpodron.orderForm) {
      return;
    }

    const GENERAL_ERROR_CODE = "FORM_GENERAL_ERROR";
    const FIELD_VALIDATION_ERROR_CODE = "FIELD_VALIDATION_ERROR";

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

    type OrderFormPropsType = {
      element: HTMLFormElement;
      config?: OrderConfigType;
    };

    class OrderForm {
      element: HTMLFormElement;

      action: string = "";

      isDisabled: boolean = false;

      errorContainer: HTMLElement;
      successContainer: HTMLElement;

      captchaLoaded:
        | (Promise<any> & {
            resolve: () => void;
          })
        | null = null;
      captchaKey: string | null = null;

      constructor({ element, config = {} }: OrderFormPropsType) {
        this.element = element;

        this.element.removeEventListener("input", this.handleFormInput);
        this.element.addEventListener("input", this.handleFormInput);

        this.element.removeEventListener("submit", this.handleFormSubmit);
        this.element.addEventListener("submit", this.handleFormSubmit);

        this.errorContainer = document.createElement("div");
        this.element.prepend(this.errorContainer);
        this.successContainer = document.createElement("div");
        this.element.prepend(this.successContainer);

        this.captchaKey = this.element.getAttribute("data-captcha");
        this.action = this.element.getAttribute("action") || "";

        if (this.captchaKey) {
          this.disable();

          this.captchaLoaded = (window as any).welpodron.templater.deferred();

          if (!(window as any).grecaptcha) {
            const loadCaptcha = () => {
              if (document.querySelector(`script[src*="recaptcha"]`)) {
                if (this.element.checkValidity()) {
                  this.enable();
                }
                this.captchaLoaded?.resolve();
                return;
              }
              const script = document.createElement("script");
              script.src = `https://www.google.com/recaptcha/api.js?render=${this.captchaKey}`;
              document.body.appendChild(script);
              script.onload = () => {
                ((window as any).grecaptcha as any).ready(() => {
                  if (this.element.checkValidity()) {
                    this.enable();
                  }
                  this.captchaLoaded?.resolve();
                });
              };
            };

            window.addEventListener("scroll", loadCaptcha, {
              once: true,
              passive: true,
            });
            window.addEventListener("touchstart", loadCaptcha, {
              once: true,
            });
            document.addEventListener("mouseenter", loadCaptcha, {
              once: true,
            });
            document.addEventListener("click", loadCaptcha, {
              once: true,
            });
          } else {
            ((window as any).grecaptcha as any).ready(() => {
              if (this.element.checkValidity()) {
                this.enable();
              }
              this.captchaLoaded?.resolve();
            });
          }
        }

        // v4
        this.disable();

        if (this.element.checkValidity()) {
          this.enable();
        }
      }

      handleFormSubmit = async (event: Event) => {
        event.preventDefault();

        if (!this.action.trim().length) {
          return;
        }

        if (this.isDisabled) {
          return;
        }

        this.disable();

        const data = new FormData(this.element);

        if (this.captchaKey) {
          const token = await ((window as any).grecaptcha as any).execute(
            this.captchaKey,
            { action: "submit" }
          );
          data.set("g-recaptcha-response", token);
        }

        try {
          const response = await fetch(this.action, {
            method: "POST",
            body: data,
          });

          if (!response.ok) {
            this.enable();
            console.error(response);
            return;
          }

          const result: _BitrixResponse = await response.json();

          if (result.status === "error") {
            const error = result.errors[0];

            if (error.code === FIELD_VALIDATION_ERROR_CODE) {
              const field = this.element.elements[error.customData as any] as
                | HTMLInputElement
                | HTMLTextAreaElement
                | HTMLSelectElement;

              if (field) {
                field.setCustomValidity(error.message);
                field.reportValidity();
                field.addEventListener(
                  "input",
                  () => {
                    field.setCustomValidity("");
                    field.reportValidity();
                    field.checkValidity();
                  },
                  {
                    once: true,
                  }
                );
              }
            }

            if (error.code === GENERAL_ERROR_CODE) {
              (window as any).welpodron.templater.renderHTML({
                string: error.message,
                container: this.errorContainer,
                config: {
                  replace: true,
                },
              });
            }

            this.enable();
            console.error(error);
            return;
          }

          if (result.status === "success") {
            (window as any).welpodron.templater.renderHTML({
              string: result.data,
              container: this.successContainer,
              config: {
                replace: true,
              },
            });

            this.element.reset();
            if (this.element.checkValidity()) {
              this.enable();
            } else {
              this.disable();
            }
          }
        } catch (error) {
          console.error(error);
        } finally {
          if (this.element.checkValidity()) {
            this.enable();
          } else {
            this.disable();
          }
        }
      };

      // v4
      handleFormInput = (event: Event) => {
        if (this.element.checkValidity()) {
          return this.enable();
        }

        this.disable();
      };

      // v4
      disable = () => {
        this.isDisabled = true;

        [...this.element.elements]
          .filter((element) => {
            return (
              (element instanceof HTMLButtonElement ||
                element instanceof HTMLInputElement) &&
              element.type === "submit"
            );
          })
          .forEach((element) => {
            element.setAttribute("disabled", "");
          });
      };

      // v4
      enable = () => {
        this.isDisabled = false;

        [...this.element.elements]
          .filter((element) => {
            return (
              (element instanceof HTMLButtonElement ||
                element instanceof HTMLInputElement) &&
              element.type === "submit"
            );
          })
          .forEach((element) => {
            element.removeAttribute("disabled");
          });
      };
    }

    (window as any).welpodron.orderForm = OrderForm;
  }
})();
