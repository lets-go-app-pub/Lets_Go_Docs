from django import forms

class GetPhoneNumberForm(forms.Form):
    phone_number = forms.CharField(min_length=9, max_length=20)
